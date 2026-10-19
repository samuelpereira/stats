function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('stats-collect', 1);

        request.onupgradeneeded = () => {
            const database = request.result;

            if (! database.objectStoreNames.contains('actions')) {
                const store = database.createObjectStore('actions', { keyPath: 'id' });
                store.createIndex('gameId', 'gameId');
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function readBaseline() {
    const node = document.querySelector('[data-collect-baseline]');

    if (! node) {
        return null;
    }

    try {
        return JSON.parse(node.textContent || '');
    } catch {
        return null;
    }
}

function requestToPromise(request) {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function transactionDone(transaction) {
    return new Promise((resolve, reject) => {
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });
}

async function itemsForGame(database, gameId) {
    const transaction = database.transaction('actions', 'readonly');
    const rows = await requestToPromise(transaction.objectStore('actions').index('gameId').getAll(gameId));
    await transactionDone(transaction);

    return rows.sort((left, right) => left.seq - right.seq);
}

async function putItem(database, item) {
    const transaction = database.transaction('actions', 'readwrite');
    transaction.objectStore('actions').put(item);
    await transactionDone(transaction);
}

async function deleteItem(database, id) {
    const transaction = database.transaction('actions', 'readwrite');
    transaction.objectStore('actions').delete(id);
    await transactionDone(transaction);
}

function oppositeSide(side) {
    return side === 'home' ? 'away' : 'home';
}

function fill(template, token, value) {
    return String(template ?? '').replace(token, String(value));
}

function pointsFor(type) {
    if (type === 'two_point') {
        return 2;
    }

    if (type === 'three_point') {
        return 3;
    }

    if (type === 'free_throw') {
        return 1;
    }

    return 0;
}

function createCollectQueue() {
    return {
        wire: null,
        database: null,
        ready: null,
        draining: false,
        failed: false,
        online: typeof navigator === 'undefined' || navigator.onLine,
        gameId: null,
        baseline: null,
        items: [],
        local: null,
        lines: [],
        sendingId: null,
        finishWhenEmpty: false,
        pendingType: null,
        choosingPlayer: false,
        choosingShot: false,
        shotError: false,
        shotPlayerId: null,
        shotForOpponent: false,
        choosingTimeout: false,
        substituting: false,
        courtSelection: [],
        courtError: false,
        confirmingQuarterEnd: false,
        startingPeriod: false,
        editingPendingId: null,
        editingType: null,
        editingPlayerId: null,
        editingForOpponent: false,
        editingEventClock: null,

        bind(wire) {
            this.wire = wire;
            this.refreshBaseline();
        },

        refreshBaseline() {
            const baseline = readBaseline();

            if (! baseline) {
                return;
            }

            const gameChanged = this.gameId !== null && this.gameId !== baseline.gameId;
            this.baseline = baseline;
            this.gameId = baseline.gameId;

            if (! this.ready || gameChanged) {
                this.ready = this.prepare();
                this.ready.then(() => this.drain());

                return;
            }

            if (! this.draining) {
                this.replay();
            }
        },

        async prepare() {
            const gameId = this.gameId;
            this.database = this.database ?? await openDatabase();
            const rows = await itemsForGame(this.database, gameId);

            if (this.gameId !== gameId) {
                return;
            }

            this.items = rows;
            this.replay();
        },

        replay() {
            if (! this.baseline) {
                this.local = null;
                this.lines = [];

                return;
            }

            let court = this.baseline.court.slice();
            let quarter = this.baseline.quarter;
            let awaitingQuarterStart = this.baseline.awaitingQuarterStart;
            let quarterEnded = this.baseline.quarterEnded;
            let periodOpen = this.baseline.periodOpen;
            let gameStarted = this.baseline.gameStarted;
            let teamPoints = this.baseline.teamPoints ?? 0;
            let opponentPoints = this.baseline.opponentPoints ?? 0;
            const lines = [];

            this.items.forEach((item) => {
                if (item.operation === 'record') {
                    lines.push(this.recordLine(item));
                    const points = pointsFor(item.payload.type);

                    if (item.payload.forOpponent) {
                        opponentPoints += points;
                    } else {
                        teamPoints += points;
                    }
                }

                if (item.operation === 'substitution') {
                    lines.push(this.substitutionLine(item, court));
                    court = (item.payload.playerIds ?? []).map((id) => Number(id));
                }

                if (item.operation === 'timeout') {
                    lines.push(this.timeoutLine(item));
                }

                if (item.operation === 'period' && item.payload.type === 'quarter_ended') {
                    lines.push(this.periodLine(item));
                    awaitingQuarterStart = true;
                    quarterEnded = false;
                    periodOpen = false;
                    quarter = Number(item.payload.quarter) + 1;
                }

                if (item.operation === 'period' && item.payload.type === 'quarter_started') {
                    lines.push(this.periodLine(item));
                    awaitingQuarterStart = false;
                    quarterEnded = false;
                    periodOpen = true;
                    gameStarted = true;
                    quarter = Number(item.payload.quarter);
                }
            });

            this.local = {
                court,
                quarter,
                awaitingQuarterStart,
                quarterEnded,
                periodOpen,
                gameStarted,
                teamPoints,
                opponentPoints,
            };
            this.lines = lines.reverse();
        },

        async enqueue(operation, payload) {
            await this.ready;

            const item = {
                id: crypto.randomUUID(),
                gameId: this.gameId,
                seq: this.items.reduce((max, queued) => Math.max(max, queued.seq ?? 0), 0) + 1,
                operation,
                payload,
            };

            this.items.push(item);
            await putItem(this.database, item);
            this.failed = false;
            this.replay();
            this.drain();
        },

        async remove(id) {
            this.items = this.items.filter((item) => item.id !== id);

            if (this.database) {
                await deleteItem(this.database, id);
            }
        },

        async drain() {
            if (this.draining || ! this.wire || ! this.database) {
                return;
            }

            this.draining = true;

            try {
                while (this.items.length > 0) {
                    const item = this.items[0];
                    this.sendingId = item.id;

                    try {
                        await this.wire.applyCollectedAction(item.id, item.operation, item.payload);
                        this.failed = false;
                        await this.remove(item.id);
                        this.replay();
                    } catch {
                        this.failed = true;
                        this.sendingId = null;
                        break;
                    }

                    this.sendingId = null;
                }
            } finally {
                this.draining = false;
                this.refreshBaseline();
            }

            if (! this.failed && this.items.length > 0) {
                this.drain();
            }

            if (this.finishWhenEmpty && this.items.length === 0) {
                this.finishWhenEmpty = false;
                this.wire.finishGameAndExit();
            }
        },

        pendingCount() {
            return this.items.length;
        },

        periodOpen() {
            return this.local ? this.local.periodOpen : false;
        },

        awaitingStart() {
            return Boolean(this.local?.awaitingQuarterStart);
        },

        teamScore() {
            return this.local?.teamPoints ?? this.baseline?.teamPoints ?? 0;
        },

        opponentScore() {
            return this.local?.opponentPoints ?? this.baseline?.opponentPoints ?? 0;
        },

        currentQuarter() {
            return Number(this.local?.quarter ?? this.baseline?.quarter ?? 1);
        },

        timeoutQuarters(quarter) {
            const period = Number(quarter);

            if (period <= 2) {
                return [1, 2];
            }

            if (period <= 4) {
                return [3, 4];
            }

            return [period];
        },

        timeoutAllowance() {
            const quarter = this.currentQuarter();

            if (quarter <= 2) {
                return 2;
            }

            if (quarter <= 4) {
                return 3;
            }

            return 1;
        },

        sameTimeoutWindow() {
            return this.timeoutQuarters(this.currentQuarter())[0] === this.timeoutQuarters(this.baseline?.quarter)[0];
        },

        queuedFouls(forOpponent) {
            const quarter = this.currentQuarter();

            return this.items.filter((item) => item.operation === 'record'
                && item.payload.type === 'foul'
                && Boolean(item.payload.forOpponent) === forOpponent
                && Number(item.payload.quarter) === quarter).length;
        },

        teamFoulCount() {
            const recorded = Number(this.baseline?.quarter) === this.currentQuarter()
                ? Number(this.baseline?.teamFouls ?? 0)
                : 0;

            return recorded + this.queuedFouls(false);
        },

        opponentFoulCount() {
            const recorded = Number(this.baseline?.quarter) === this.currentQuarter()
                ? Number(this.baseline?.opponentFouls ?? 0)
                : 0;

            return recorded + this.queuedFouls(true);
        },

        queuedTimeouts(ours) {
            const quarters = new Set(this.timeoutQuarters(this.currentQuarter()));

            return this.items.filter((item) => item.operation === 'timeout'
                && quarters.has(Number(item.payload.quarter))
                && (item.payload.side === this.baseline?.homeAway) === ours).length;
        },

        teamTimeoutCount() {
            const recorded = this.sameTimeoutWindow() ? Number(this.baseline?.teamTimeouts ?? 0) : 0;

            return recorded + this.queuedTimeouts(true);
        },

        opponentTimeoutCount() {
            const recorded = this.sameTimeoutWindow() ? Number(this.baseline?.opponentTimeouts ?? 0) : 0;

            return recorded + this.queuedTimeouts(false);
        },

        foulTone(count) {
            if (count >= 5) {
                return 'text-red-700';
            }

            if (count === 4) {
                return 'text-orange-600';
            }

            if (count >= 1) {
                return 'text-green-700';
            }

            return 'text-zinc-900';
        },

        timeoutTone(count) {
            return count >= this.timeoutAllowance() ? 'text-red-700' : 'text-zinc-900';
        },

        gameFinished() {
            return Boolean(this.baseline?.finished);
        },

        quarterEnded() {
            return this.local ? this.local.quarterEnded : false;
        },

        statEnabled(type) {
            if (! this.local) {
                return false;
            }

            if (type === 'substitution') {
                return ! this.local.gameStarted || this.local.periodOpen;
            }

            return this.local.periodOpen;
        },

        canUndo() {
            return this.removableItem() !== null || this.sendingId !== null || Boolean(this.baseline?.canUndo);
        },

        periodDiffers() {
            if (! this.local || ! this.baseline) {
                return false;
            }

            return this.local.quarter !== this.baseline.quarter
                || this.local.awaitingQuarterStart !== this.baseline.awaitingQuarterStart;
        },

        periodLabel() {
            const quarter = this.local?.quarter ?? 1;
            const templates = this.baseline?.templates ?? {};

            if (this.local?.awaitingQuarterStart) {
                if (quarter > 4) {
                    return fill(templates.startOfOvertime, ':overtime', quarter - 4);
                }

                return fill(templates.startOfQuarter, ':quarter', quarter);
            }

            return quarter > 4 ? `OT${quarter - 4}` : `Q${quarter}`;
        },

        endPeriodQuestion() {
            const quarter = this.local?.quarter ?? 1;
            const templates = this.baseline?.templates ?? {};

            if (quarter > 4) {
                return fill(templates.endOvertimeQuestion, ':overtime', quarter - 4);
            }

            return fill(templates.endQuarterQuestion, ':quarter', quarter);
        },

        endPeriodAction() {
            const quarter = this.local?.quarter ?? 1;
            const templates = this.baseline?.templates ?? {};

            if (quarter > 4) {
                return fill(templates.endOvertime, ':overtime', quarter - 4);
            }

            return fill(templates.endQuarter, ':quarter', quarter);
        },

        startPeriodAction() {
            const quarter = this.local?.quarter ?? 1;
            const templates = this.baseline?.templates ?? {};

            if (quarter > 4) {
                return fill(templates.startOvertime, ':overtime', quarter - 4);
            }

            return fill(templates.startQuarter, ':quarter', quarter);
        },

        clock() {
            return window.Alpine.store('gameClock')?.formatted ?? '00:00';
        },

        select(type) {
            if (! this.statEnabled(type)) {
                return;
            }

            if (type === 'substitution') {
                if (! this.local.gameStarted) {
                    this.wire.select('substitution');

                    return;
                }

                this.pendingType = null;
                this.choosingPlayer = false;
                this.courtSelection = this.local.court.slice();
                this.courtError = false;
                this.substituting = true;

                return;
            }

            this.substituting = false;
            this.pendingType = this.pendingType === type ? null : type;
            this.choosingPlayer = this.pendingType !== null;
        },

        courtPlayers() {
            const court = new Set(this.local?.court ?? []);

            return (this.baseline?.roster ?? []).filter((player) => court.has(player.id));
        },

        benchPlayers() {
            const court = new Set(this.local?.court ?? []);

            return (this.baseline?.roster ?? []).filter((player) => ! court.has(player.id));
        },

        foulCount(playerId) {
            const quarter = Number(this.local?.quarter ?? this.baseline?.quarter);
            const player = (this.baseline?.roster ?? []).find((rosterPlayer) => rosterPlayer.id === Number(playerId));
            const recorded = Number(this.baseline?.quarter) === quarter ? Number(player?.fouls ?? 0) : 0;
            const pending = this.items.filter((item) => item.operation === 'record'
                && item.payload.type === 'foul'
                && ! item.payload.forOpponent
                && Number(item.payload.playerId) === Number(playerId)
                && Number(item.payload.quarter) === quarter).length;

            return recorded + pending;
        },

        foulMarks(playerId) {
            return Array.from({ length: this.foulCount(playerId) }, (_, index) => index);
        },

        pickerTitle() {
            return this.baseline?.pickerTitles?.[this.pendingType]
                ?? this.baseline?.labels?.[this.pendingType]
                ?? '';
        },

        needsShot(type) {
            return Boolean(this.baseline?.markShotLocation) && (this.baseline?.fieldGoals ?? []).includes(type);
        },

        insideThreePointLine(x, y) {
            const court = this.baseline?.shotCourt;

            if (! court || x < court.cornerX || x > court.width - court.cornerX) {
                return false;
            }

            return Math.hypot(x - court.hoopX, y - court.hoopY) <= court.radius;
        },

        allowsShot(type, x, y) {
            const court = this.baseline?.shotCourt;

            if (! court || x < court.left || x > court.right || y < court.top || y > court.bottom) {
                return false;
            }

            const inside = this.insideThreePointLine(x, y);

            if (type === 'two_point' || type === 'two_point_missed') {
                return inside;
            }

            if (type === 'three_point' || type === 'three_point_missed') {
                return ! inside;
            }

            return false;
        },

        async recordPlayer(playerId) {
            if (this.needsShot(this.pendingType)) {
                this.shotPlayerId = playerId;
                this.shotForOpponent = false;
                this.choosingPlayer = false;
                this.shotError = false;
                this.choosingShot = true;

                return;
            }

            await this.enqueue('record', {
                type: this.pendingType,
                playerId,
                forOpponent: false,
                quarter: this.local.quarter,
                clock: this.clock(),
                courtX: null,
                courtY: null,
            });
            this.closePicker();
        },

        async recordOpponent() {
            if (this.needsShot(this.pendingType)) {
                this.shotPlayerId = null;
                this.shotForOpponent = true;
                this.choosingPlayer = false;
                this.shotError = false;
                this.choosingShot = true;

                return;
            }

            await this.enqueue('record', {
                type: this.pendingType,
                playerId: null,
                forOpponent: true,
                quarter: this.local.quarter,
                clock: this.clock(),
                courtX: null,
                courtY: null,
            });
            this.closePicker();
        },

        async recordShot(courtX, courtY) {
            if (! this.allowsShot(this.pendingType, courtX, courtY)) {
                this.shotError = true;

                return false;
            }

            this.shotError = false;

            await this.enqueue('record', {
                type: this.pendingType,
                playerId: this.shotForOpponent ? null : this.shotPlayerId,
                forOpponent: this.shotForOpponent,
                quarter: this.local.quarter,
                clock: this.clock(),
                courtX,
                courtY,
            });
            this.closePicker();

            return true;
        },

        closePicker() {
            this.pendingType = null;
            this.choosingPlayer = false;
            this.choosingShot = false;
            this.shotPlayerId = null;
            this.shotForOpponent = false;
        },

        cancelShot() {
            this.choosingShot = false;
            this.shotError = false;
            this.shotPlayerId = null;
            this.shotForOpponent = false;
            this.choosingPlayer = this.pendingType !== null;
        },

        openTimeout() {
            if (! this.periodOpen()) {
                return;
            }

            this.pendingType = null;
            this.choosingPlayer = false;
            this.choosingTimeout = true;
        },

        async recordTimeout(side) {
            await this.enqueue('timeout', {
                side,
                quarter: this.local.quarter,
                clock: this.clock(),
            });
            this.choosingTimeout = false;
        },

        toggleCourt(playerId) {
            const selected = this.courtSelection.includes(playerId);

            if (! selected && this.courtSelection.length >= 5) {
                return;
            }

            this.courtError = false;
            this.courtSelection = selected
                ? this.courtSelection.filter((id) => id !== playerId)
                : this.courtSelection.concat([playerId]);
        },

        async confirmSubstitution() {
            if (this.courtSelection.length !== 5) {
                this.courtError = true;

                return;
            }

            await this.enqueue('substitution', {
                playerIds: this.courtSelection.slice(),
                quarter: this.local.quarter,
                clock: this.clock(),
            });
            this.substituting = false;
            this.courtSelection = [];
        },

        async startQuarter() {
            if (! this.local?.awaitingQuarterStart || this.startingPeriod || this.gameFinished()) {
                return;
            }

            this.startingPeriod = true;
            const clock = this.openingClock();
            const quarter = this.local.quarter;

            try {
                await this.enqueue('period', {
                    type: 'quarter_started',
                    quarter,
                    clock,
                });
                window.Alpine.store('gameClock')?.begin(clock);
            } finally {
                this.startingPeriod = false;
            }
        },

        async recordQuarterStart(clock) {
            if (! this.local || this.local.periodOpen || this.local.awaitingQuarterStart || this.local.quarterEnded || this.startingPeriod) {
                return;
            }

            this.startingPeriod = true;

            try {
                await this.enqueue('period', {
                    type: 'quarter_started',
                    quarter: this.local.quarter,
                    clock,
                });
            } finally {
                this.startingPeriod = false;
            }
        },

        askToEndQuarter() {
            const clock = window.Alpine.store('gameClock');

            if (! this.local?.periodOpen || clock?.remaining !== 0) {
                return;
            }

            this.confirmingQuarterEnd = true;
        },

        async confirmEndQuarter() {
            const quarter = this.local.quarter;
            this.confirmingQuarterEnd = false;

            await this.enqueue('period', {
                type: 'quarter_ended',
                quarter,
                clock: '00:00',
            });
            window.Alpine.store('gameClock')?.hold();
        },

        openingClock() {
            const quarter = this.local?.quarter ?? 1;
            const minutes = quarter > 4
                ? this.baseline.overtimeMinutes
                : this.baseline.quarterMinutes;

            return `${String(minutes).padStart(2, '0')}:00`;
        },

        removableItem() {
            for (let index = this.items.length - 1; index >= 0; index -= 1) {
                const item = this.items[index];

                if (item.id === this.sendingId || item.operation === 'undo' || item.operation === 'edit') {
                    continue;
                }

                return item;
            }

            return null;
        },

        async undo() {
            const item = this.removableItem();

            if (item) {
                await this.remove(item.id);
                this.replay();

                return;
            }

            if (! this.canUndo()) {
                return;
            }

            await this.enqueue('undo', {});
        },

        async saveConfirmedEdit() {
            const eventId = this.wire?.editingEventId;
            const type = this.wire?.editingType;

            if (! eventId || ! type) {
                return;
            }

            await this.enqueue('edit', {
                eventId,
                type,
                playerId: this.wire.editingForOpponent ? null : this.wire.editingPlayerId,
                outgoingPlayerId: this.wire.editingOutgoingPlayerId,
                forOpponent: Boolean(this.wire.editingForOpponent),
                clock: this.wire.editingEventClock,
            });
            this.wire.cancelEditingAction();
        },

        editableTypesForEdit() {
            const current = this.editingType;
            const groups = this.baseline?.typeSwapGroups ?? [];
            const group = groups.find((options) => options.includes(current));

            if (! group || group.length <= 1) {
                return [];
            }

            return (this.baseline?.editableTypes ?? []).filter((stat) => group.includes(stat.value));
        },

        editPending(id) {
            const item = this.items.find((queued) => queued.id === id);

            if (! item || item.id === this.sendingId || item.operation !== 'record') {
                return;
            }

            this.editingPendingId = id;
            this.editingType = item.payload.type;
            this.editingPlayerId = item.payload.playerId;
            this.editingForOpponent = Boolean(item.payload.forOpponent);
            this.editingEventClock = item.payload.clock;
        },

        async savePendingEdit() {
            const item = this.items.find((queued) => queued.id === this.editingPendingId);

            if (! item || item.id === this.sendingId) {
                return;
            }

            const type = this.editingType;
            const originalType = item.payload.type;
            const groups = this.baseline?.typeSwapGroups ?? [];
            const group = groups.find((options) => options.includes(originalType));

            if (type !== originalType && (! group || ! group.includes(type))) {
                return;
            }

            const needsShot = this.needsShot(type);
            const hasShot = item.payload.courtX !== null && item.payload.courtY !== null;

            if (needsShot && ! hasShot) {
                return;
            }

            item.payload = {
                ...item.payload,
                type,
                playerId: this.editingForOpponent ? null : this.editingPlayerId,
                forOpponent: this.editingForOpponent,
                clock: this.editingEventClock ?? item.payload.clock,
            };
            await putItem(this.database, item);
            this.editingPendingId = null;
            this.replay();
        },

        finishGame() {
            if (this.items.length > 0) {
                this.finishWhenEmpty = true;
                this.drain();

                return;
            }

            this.wire.finishGameAndExit();
        },

        onCourt(playerId) {
            return (this.local?.court ?? []).includes(playerId);
        },

        recordLine(item) {
            const ours = ! item.payload.forOpponent;
            const player = ours
                ? (this.baseline.roster.find((rosterPlayer) => rosterPlayer.id === Number(item.payload.playerId))?.label ?? '')
                : this.baseline.opponentName;

            return {
                id: item.id,
                clock: item.payload.clock,
                player,
                playerLines: [],
                label: this.baseline.labels[item.payload.type] ?? item.payload.type,
                side: ours ? this.baseline.homeAway : oppositeSide(this.baseline.homeAway),
                editable: true,
                missed: String(item.payload.type).includes('missed'),
            };
        },

        substitutionLine(item, court) {
            const next = new Set((item.payload.playerIds ?? []).map((id) => Number(id)));
            const previous = new Set(court);
            const entering = this.baseline.roster.filter((player) => next.has(player.id) && ! previous.has(player.id));
            const leaving = this.baseline.roster.filter((player) => previous.has(player.id) && ! next.has(player.id));

            return {
                id: item.id,
                clock: item.payload.clock,
                player: '',
                playerLines: [
                    ...entering.map((player) => ({ enters: true, name: player.label })),
                    ...leaving.map((player) => ({ enters: false, name: player.label })),
                ],
                label: this.baseline.labels.substitution ?? 'sub',
                side: this.baseline.homeAway,
                editable: false,
                missed: false,
            };
        },

        timeoutLine(item) {
            const ours = item.payload.side === this.baseline.homeAway;

            return {
                id: item.id,
                clock: item.payload.clock,
                player: ours ? this.baseline.teamName : this.baseline.opponentName,
                playerLines: [],
                label: this.baseline.labels.timeout ?? 'timeout',
                side: item.payload.side,
                editable: false,
                missed: false,
            };
        },

        periodLine(item) {
            const started = item.payload.type === 'quarter_started';

            return {
                id: item.id,
                clock: item.payload.clock,
                player: '',
                playerLines: [],
                label: this.baseline.labels[item.payload.type] ?? (started ? 'period start' : 'period end'),
                side: null,
                editable: false,
                missed: false,
            };
        },
    };
}

function registerCollectQueue() {
    if (window.Alpine.store('collectQueue')) {
        return;
    }

    window.Alpine.store('collectQueue', createCollectQueue());

    window.addEventListener('online', () => {
        const queue = window.Alpine.store('collectQueue');

        if (! queue) {
            return;
        }

        queue.online = true;
        queue.drain();
    });

    window.addEventListener('offline', () => {
        const queue = window.Alpine.store('collectQueue');

        if (! queue) {
            return;
        }

        queue.online = false;
    });
}

document.addEventListener('alpine:init', registerCollectQueue);

if (window.Alpine) {
    registerCollectQueue();
}

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            window.Alpine.store('collectQueue')?.refreshBaseline();
        });
    });
});
