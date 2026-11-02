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

function normalizeGameId(gameId) {
    const id = Number(gameId);

    return Number.isFinite(id) ? id : gameId;
}

async function itemsForGame(database, gameId) {
    const key = normalizeGameId(gameId);
    const readByKey = async (indexKey) => {
        const transaction = database.transaction('actions', 'readonly');
        const rows = await requestToPromise(transaction.objectStore('actions').index('gameId').getAll(indexKey));
        await transactionDone(transaction);

        return rows;
    };

    let rows = await readByKey(key);

    // Older queued rows may have stored gameId as a string.
    if (rows.length === 0 && String(key) !== String(gameId)) {
        rows = await readByKey(String(gameId));
    } else if (rows.length === 0 && typeof key === 'number') {
        rows = await readByKey(String(key));
    }

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

function runningScoreLabel(homeAway, teamPoints, opponentPoints) {
    if (homeAway === 'home') {
        return `${teamPoints}-${opponentPoints}`;
    }

    return `${opponentPoints}-${teamPoints}`;
}

function formatPlayedSeconds(total) {
    const seconds = Math.max(0, Number(total) || 0);

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

function normalizeSecondsMap(seconds) {
    const normalized = {};

    Object.entries(seconds ?? {}).forEach(([playerId, value]) => {
        normalized[Number(playerId)] = Math.max(0, Number(value) || 0);
    });

    return normalized;
}

function toPlain(value) {
    if (value === undefined) {
        return undefined;
    }

    return JSON.parse(JSON.stringify(value));
}

function durableQueueItem(item) {
    const durable = {
        id: item.id,
        gameId: item.gameId,
        seq: item.seq,
        operation: item.operation,
        payload: toPlain(item.payload) ?? {},
    };

    if (item.attempted) {
        durable.attempted = true;
    }

    if (item.revert) {
        durable.revert = toPlain(item.revert);
    }

    return durable;
}

function shiftScoreLabel(label, homeAway, teamDelta, opponentDelta) {
    const match = String(label ?? '').match(/^(\d+)-(\d+)$/);

    if (! match || (teamDelta === 0 && opponentDelta === 0)) {
        return label;
    }

    const left = Number(match[1]);
    const right = Number(match[2]);

    if (homeAway === 'home') {
        return runningScoreLabel(homeAway, left + teamDelta, right + opponentDelta);
    }

    return runningScoreLabel(homeAway, right + teamDelta, left + opponentDelta);
}

function createCollectQueue() {
    return {
        wire: null,
        database: null,
        ready: null,
        draining: false,
        failed: false,
        autoRetryCount: 0,
        maxAutoRetries: 5,
        retryTimer: null,
        syncChunkSize: 1,
        syncTimeoutMs: 45000,
        drainEpoch: 0,
        lastSyncError: null,
        online: typeof navigator === 'undefined' || navigator.onLine,
        gameId: null,
        baseline: null,
        items: [],
        local: null,
        lines: [],
        serverLines: [],
        committedLines: [],
        sendingId: null,
        finishWhenEmpty: false,
        pendingType: null,
        pendingFoulKind: null,
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
        editingEventId: null,
        editingOriginalType: null,
        editingType: null,
        editingPlayerId: null,
        editingOutgoingPlayerId: null,
        editingForOpponent: false,
        editingFoulKind: null,
        editingEventClock: null,
        editingPlayerLines: [],
        editingCourtBefore: [],
        editingCourtX: null,
        editingCourtY: null,
        seconds: {},
        playingMark: null,

        bind(wire) {
            this.wire = wire;
            this.refreshBaseline();
        },

        refreshBaseline(retry = true) {
            if (this.draining) {
                return;
            }

            const baseline = readBaseline();

            if (! baseline) {
                if (retry && document.querySelector('[data-statistics-record]')) {
                    requestAnimationFrame(() => this.refreshBaseline(false));
                }

                return;
            }

            const nextGameId = normalizeGameId(baseline.gameId);
            const gameChanged = this.gameId !== null && this.gameId !== nextGameId;
            this.baseline = baseline;
            this.gameId = nextGameId;
            this.applyCommittedLines(baseline.lines);

            if (! this.ready || gameChanged) {
                this.ready = this.prepare();
                this.ready.then(() => {
                    this.adoptSeconds(baseline.seconds);
                    this.drain();
                });

                return;
            }

            this.replay();
            this.adoptSeconds(baseline.seconds);
        },

        applyCommittedLines(lines) {
            if (! Array.isArray(lines)) {
                return;
            }

            this.serverLines = lines;
            this.committedLines = lines;
        },

        mergeSyncState(result) {
            if (! result?.baseline || ! this.baseline) {
                return;
            }

            Object.assign(this.baseline, result.baseline);
            this.applyCommittedLines(result.baseline.lines);
            this.adoptSeconds(result.baseline.seconds);
            // Do not push gameClock from sync: a late offline backlog can carry an
            // older (higher) clock and jump the pad backward without user action.

            const node = document.querySelector('[data-collect-baseline]');

            if (! node) {
                return;
            }

            try {
                const current = JSON.parse(node.textContent || '{}');
                node.textContent = JSON.stringify({ ...current, ...result.baseline });
            } catch {
                // Keep the in-memory baseline when the DOM node is not JSON.
            }
        },

        playingPeriodOpen() {
            return Boolean(this.local?.periodOpen ?? this.baseline?.periodOpen);
        },

        adoptSeconds(seconds) {
            this.seconds = normalizeSecondsMap(seconds);

            const clock = window.Alpine?.store('gameClock');

            if (clock?.running && this.playingPeriodOpen()) {
                this.playingMark = clock.remaining;

                return;
            }

            this.playingMark = null;
        },

        markPlayingStart(clockSeconds) {
            if (! this.playingPeriodOpen()) {
                this.playingMark = null;

                return;
            }

            this.playingMark = Math.max(0, Number(clockSeconds) || 0);
        },

        accruePlayingTime(clockSeconds) {
            if (this.playingMark === null || ! this.playingPeriodOpen()) {
                this.playingMark = null;

                return;
            }

            const mark = Math.max(0, Number(clockSeconds) || 0);
            const delta = Math.max(0, this.playingMark - mark);
            const court = this.local?.court ?? this.baseline?.court ?? [];
            const next = { ...this.seconds };

            court.forEach((playerId) => {
                const id = Number(playerId);
                next[id] = (next[id] ?? 0) + delta;
            });

            this.seconds = next;
            this.playingMark = null;
        },

        playedSeconds(playerId) {
            const id = Number(playerId);

            return Number(this.seconds?.[id] ?? 0);
        },

        playedLabel(playerId) {
            return formatPlayedSeconds(this.playedSeconds(playerId));
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
            const edited = this.overlayPendingEdits();
            this.committedLines = edited.lines;
            let teamPoints = (this.baseline.teamPoints ?? 0) + edited.teamDelta;
            let opponentPoints = (this.baseline.opponentPoints ?? 0) + edited.opponentDelta;
            const lines = [];

            this.items.forEach((item) => {
                if (item.operation === 'record') {
                    const points = pointsFor(item.payload.type);

                    if (item.payload.forOpponent) {
                        opponentPoints += points;
                    } else {
                        teamPoints += points;
                    }

                    lines.push(this.recordLine(item, teamPoints, opponentPoints));
                }

                if (item.operation === 'substitution') {
                    lines.push(this.substitutionLine(item, court, teamPoints, opponentPoints));
                    court = (item.payload.playerIds ?? []).map((id) => Number(id));
                }

                if (item.operation === 'timeout') {
                    lines.push(this.timeoutLine(item, teamPoints, opponentPoints));
                }

                if (item.operation === 'period' && item.payload.type === 'quarter_ended') {
                    lines.push(this.periodLine(item, teamPoints, opponentPoints));
                    awaitingQuarterStart = true;
                    quarterEnded = false;
                    periodOpen = false;
                    quarter = Number(item.payload.quarter) + 1;
                }

                if (item.operation === 'period' && item.payload.type === 'quarter_started') {
                    lines.push(this.periodLine(item, teamPoints, opponentPoints));
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
            this.lines = [...lines].reverse();
        },

        // Server lines are never patched in place: queued edits are re-applied on
        // every replay so they survive syncs of older items, failures and reloads.
        overlayPendingEdits() {
            const homeAway = this.baseline?.homeAway;
            let lines = this.serverLines ?? [];
            let teamDelta = 0;
            let opponentDelta = 0;

            this.items.forEach((item) => {
                if (item.operation !== 'edit') {
                    return;
                }

                const index = lines.findIndex((line) => Number(line.id) === Number(item.payload.eventId));
                const current = index >= 0 ? lines[index] : null;
                const before = current ?? item.revert ?? null;

                if (! before) {
                    return;
                }

                const [team, opponent] = this.editPointsDelta(before, item.payload);
                teamDelta += team;
                opponentDelta += opponent;

                if (index < 0) {
                    return;
                }

                const next = this.editedLine(current, item.payload);

                lines = lines.map((line, position) => {
                    if (position > index) {
                        return line;
                    }

                    const base = position === index ? next : line;

                    return { ...base, score: shiftScoreLabel(base.score, homeAway, team, opponent) };
                });
            });

            return { lines, teamDelta, opponentDelta };
        },

        editPointsDelta(before, after) {
            const oldPoints = pointsFor(before.type ?? before.actionKey);
            const newPoints = pointsFor(after.type);
            let team = 0;
            let opponent = 0;

            if (before.forOpponent) {
                opponent -= oldPoints;
            } else {
                team -= oldPoints;
            }

            if (after.forOpponent) {
                opponent += newPoints;
            } else {
                team += newPoints;
            }

            return [team, opponent];
        },

        releaseStuckDrainFlag() {
            // A hung Livewire request can leave draining=true forever after sendingId clears.
            if (this.draining && this.sendingId === null) {
                this.draining = false;
            }
        },

        async enqueue(operation, payload, meta = {}) {
            await this.ready;

            const plainMeta = {};

            Object.entries(meta).forEach(([key, value]) => {
                plainMeta[key] = toPlain(value);
            });

            const item = {
                id: crypto.randomUUID(),
                gameId: normalizeGameId(this.gameId),
                seq: this.items.reduce((max, queued) => Math.max(max, queued.seq ?? 0), 0) + 1,
                operation,
                payload: toPlain(payload) ?? {},
                ...plainMeta,
            };

            this.items.push(item);
            await putItem(this.database, durableQueueItem(item));
            this.clearSyncFailure();
            this.replay();
            this.releaseStuckDrainFlag();
            this.drain();
        },

        cancelFor(item) {
            const payload = { targetId: item.id };

            if (item.operation === 'edit' && item.revert) {
                payload.revert = toPlain(item.revert);
            }

            return {
                id: crypto.randomUUID(),
                gameId: item.gameId,
                seq: item.seq,
                operation: 'cancel',
                payload,
            };
        },

        // A sent item may have been applied even when no answer arrived, so it is
        // replaced by a cancel that the server resolves either way.
        async replaceWithCancel(item) {
            const cancel = this.cancelFor(item);
            this.items = this.items.map((queued) => (queued.id === item.id ? cancel : queued));
            await putItem(this.database, durableQueueItem(cancel));
            await deleteItem(this.database, item.id);
        },

        async remove(id) {
            this.items = this.items.filter((item) => item.id !== id);

            if (this.database) {
                await deleteItem(this.database, id);
            }
        },

        reattachWire() {
            const wire = window.resolveStatisticsRecordWire?.();

            if (wire) {
                this.wire = wire;
            }
        },

        clearSyncFailure() {
            this.failed = false;
            this.autoRetryCount = 0;
            this.clearRetryTimer();
        },

        clearRetryTimer() {
            if (this.retryTimer !== null) {
                clearTimeout(this.retryTimer);
                this.retryTimer = null;
            }
        },

        showSyncError() {
            return this.online
                && this.failed
                && this.retryTimer === null
                && this.autoRetryCount >= this.maxAutoRetries
                && this.items.length > 0;
        },

        showSyncRetrying() {
            return this.online
                && this.failed
                && this.retryTimer !== null
                && this.items.length > 0;
        },

        scheduleAutoRetry() {
            if (! this.online || this.items.length === 0 || this.autoRetryCount >= this.maxAutoRetries) {
                return;
            }

            this.clearRetryTimer();

            const delays = [2000, 5000, 10000, 20000, 30000];
            const delay = delays[this.autoRetryCount] ?? 30000;
            this.autoRetryCount += 1;

            this.retryTimer = setTimeout(() => {
                this.retryTimer = null;
                this.failed = false;
                this.reattachWire();
                this.drain();
            }, delay);
        },

        retrySync() {
            if (! this.online || this.items.length === 0) {
                return;
            }

            // Invalidate any in-flight drain loop, then start a fresh one.
            this.drainEpoch += 1;
            this.draining = false;
            this.sendingId = null;
            this.clearSyncFailure();
            this.reattachWire();
            this.drain();
        },

        async consumeSyncResult(result) {
            const applied = new Set(result?.applied ?? []);

            if (applied.size === 0) {
                return applied;
            }

            for (const item of [...this.items]) {
                if (! applied.has(item.id)) {
                    continue;
                }

                await this.remove(item.id);
            }

            this.mergeSyncState(result);
            this.replay();

            return applied;
        },

        async sendBatch(batch) {
            this.reattachWire();

            if (! this.wire || typeof this.wire.applyCollectedActions !== 'function') {
                throw new Error('collect wire unavailable');
            }

            const payload = batch.map((item) => ({
                id: item.id,
                operation: item.operation,
                payload: item.payload,
            }));

            let timer = null;

            try {
                return await Promise.race([
                    this.wire.applyCollectedActions(payload),
                    new Promise((_, reject) => {
                        timer = setTimeout(() => reject(new Error('sync timeout')), this.syncTimeoutMs);
                    }),
                ]);
            } finally {
                if (timer !== null) {
                    clearTimeout(timer);
                }
            }
        },

        async drain() {
            this.reattachWire();

            if (this.ready) {
                await this.ready;
            }

            if (this.draining || ! this.online) {
                return;
            }

            if (! this.database && this.gameId !== null && this.gameId !== undefined) {
                this.database = await openDatabase();
                const rows = await itemsForGame(this.database, this.gameId);

                if (rows.length > 0 && this.items.length === 0) {
                    this.items = rows;
                    this.replay();
                }
            }

            if (! this.database) {
                return;
            }

            if (! this.wire || typeof this.wire.applyCollectedActions !== 'function') {
                if (this.items.length > 0) {
                    this.failed = true;
                    this.lastSyncError = 'wire unavailable';
                    this.scheduleAutoRetry();
                }

                return;
            }

            const epoch = ++this.drainEpoch;
            this.draining = true;
            this.clearRetryTimer();
            this.lastSyncError = null;

            try {
                while (this.items.length > 0 && this.online && epoch === this.drainEpoch) {
                    const batch = this.items.slice(0, this.syncChunkSize);
                    const head = batch[0] ?? null;
                    this.sendingId = head?.id ?? null;

                    try {
                        if (head && ! head.attempted) {
                            head.attempted = true;
                            await putItem(this.database, durableQueueItem(head));
                        }

                        const result = await this.sendBatch(batch);

                        // Undo replaced the in-flight head with a cancel, which reverses
                        // a late server apply on its own.
                        if (epoch !== this.drainEpoch) {
                            return;
                        }

                        const applied = await this.consumeSyncResult(result);

                        if (applied.size >= batch.length) {
                            this.failed = false;
                            this.lastSyncError = null;
                            continue;
                        }

                        // Keep FIFO order: never skip the head. A rejected action must
                        // block the rest of the queue until it can be applied or fixed.
                        this.failed = true;
                        this.lastSyncError = result?.error ?? 'partial apply';
                        console.warn('[collectQueue] head action rejected — queue paused', {
                            id: head?.id,
                            operation: head?.operation,
                            error: this.lastSyncError,
                            payload: head?.payload,
                        });
                        break;
                    } catch (error) {
                        if (epoch !== this.drainEpoch) {
                            return;
                        }

                        this.failed = true;
                        this.lastSyncError = error?.message ?? 'sync failed';
                        console.warn('[collectQueue] sync failed', error);
                        break;
                    } finally {
                        if (epoch === this.drainEpoch) {
                            this.sendingId = null;
                        }
                    }
                }
            } finally {
                if (epoch === this.drainEpoch) {
                    this.draining = false;
                }
            }

            if (epoch !== this.drainEpoch) {
                return;
            }

            if (this.failed) {
                this.scheduleAutoRetry();
            } else {
                this.autoRetryCount = 0;
            }

            if (! this.failed && this.items.length > 0 && this.online) {
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

        foulQuarters(quarter) {
            const period = Number(quarter);

            if (period <= 4) {
                return [period];
            }

            return Array.from({ length: period - 3 }, (_, index) => index + 4);
        },

        sameFoulWindow() {
            return this.foulQuarters(this.currentQuarter()).includes(Number(this.baseline?.quarter));
        },

        queuedFouls(forOpponent) {
            const quarters = new Set(this.foulQuarters(this.currentQuarter()));

            return this.items.filter((item) => item.operation === 'record'
                && item.payload.type === 'foul'
                && Boolean(item.payload.forOpponent) === forOpponent
                && quarters.has(Number(item.payload.quarter))).length;
        },

        teamFoulCount() {
            const recorded = this.sameFoulWindow()
                ? Number(this.baseline?.teamFouls ?? 0)
                : 0;

            return recorded + this.queuedFouls(false);
        },

        opponentFoulCount() {
            const recorded = this.sameFoulWindow()
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
                return ! this.local.gameStarted
                    || this.local.periodOpen
                    || this.local.awaitingQuarterStart;
            }

            return this.local.periodOpen;
        },

        isSaving() {
            return this.online
                && this.items.length > 0
                && ! this.showSyncError()
                && ! this.showSyncRetrying();
        },

        canUndo() {
            if (this.isSaving()) {
                return false;
            }

            if (this.removableItem() !== null) {
                return true;
            }

            return this.items.length === 0 && Boolean(this.baseline?.canUndo);
        },

        periodLabel() {
            const source = this.local ?? this.baseline;
            const quarter = Number(source?.quarter ?? 1);
            const templates = this.baseline?.templates ?? {};
            const awaiting = Boolean(source?.awaitingQuarterStart);

            if (awaiting && this.gameFinished() && quarter > 4) {
                const previous = quarter - 1;

                return previous > 4 ? `OT${previous - 4}` : `Q${previous}`;
            }

            if (awaiting) {
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

        substitutionButtonLabel(fallback) {
            const templates = this.baseline?.templates ?? {};

            if (! this.local) {
                return fallback;
            }

            return (this.local.gameStarted ? templates.substitutionButton : templates.lineupButton) ?? fallback;
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
            this.pendingFoulKind = null;
            this.choosingPlayer = this.pendingType !== null;
        },

        setFoulKind(kind) {
            if (this.pendingType !== 'foul') {
                return;
            }

            this.pendingFoulKind = kind || null;
        },

        foulKindAllowsCoach() {
            const kind = this.pendingFoulKind;

            if (! kind) {
                return false;
            }

            return (this.baseline?.foulKinds ?? []).some((option) => option.value === kind && option.allowsCoach);
        },

        foulKindLabel(kind) {
            if (! kind) {
                return this.baseline?.labels?.foul ?? 'foul';
            }

            return (this.baseline?.foulKinds ?? []).find((option) => option.value === kind)?.logLabel
                ?? kind;
        },

        courtPlayers() {
            const court = new Set(this.local?.court ?? []);

            return (this.baseline?.roster ?? []).filter((player) => court.has(player.id));
        },

        benchPlayers() {
            const court = new Set(this.local?.court ?? []);

            return (this.baseline?.roster ?? []).filter((player) => ! court.has(player.id));
        },

        editCourtPlayers() {
            const court = new Set((this.editingCourtBefore ?? []).map((id) => Number(id)));

            return (this.baseline?.roster ?? []).filter((player) => court.has(Number(player.id)));
        },

        editBenchPlayers() {
            const court = new Set((this.editingCourtBefore ?? []).map((id) => Number(id)));

            return (this.baseline?.roster ?? []).filter((player) => ! court.has(Number(player.id)));
        },

        substitutionRole(playerId) {
            const id = Number(playerId);
            const onCourt = (this.editingCourtBefore ?? []).map((courtId) => Number(courtId)).includes(id);
            const selected = this.courtSelection.map((selectedId) => Number(selectedId)).includes(id);

            if (onCourt && selected) {
                return 'stays';
            }

            if (onCourt) {
                return 'leaves';
            }

            if (selected) {
                return 'enters';
            }

            return '';
        },

        chooseSubstitutionPlayer(playerId) {
            const id = Number(playerId);
            const before = (this.editingCourtBefore ?? []).map((courtId) => Number(courtId));
            const onCourt = before.includes(id);

            if (onCourt) {
                this.editingOutgoingPlayerId = id;
            } else {
                this.editingPlayerId = id;
            }

            const outgoing = Number(this.editingOutgoingPlayerId);
            const incoming = Number(this.editingPlayerId);
            const selected = before.filter((courtId) => courtId !== outgoing);

            if (incoming && ! selected.includes(incoming)) {
                selected.push(incoming);
            }

            this.courtSelection = selected;
            this.courtError = false;
        },

        courtBeforeCommittedLine(eventId) {
            let court = (this.baseline?.court ?? []).map((id) => Number(id));
            const lines = this.serverLines ?? [];
            const index = lines.findIndex((line) => Number(line.id) === Number(eventId));

            if (index < 0) {
                return court;
            }

            lines.slice(0, index + 1).forEach((line) => {
                court = this.undoSubstitutionOnCourt(court, line);
            });

            return court;
        },

        courtBeforeQueuedItem(id) {
            let court = (this.baseline?.court ?? []).map((playerId) => Number(playerId));

            for (const item of this.items) {
                if (item.id === id) {
                    break;
                }

                if (item.operation !== 'substitution') {
                    continue;
                }

                const marked = (item.payload.playerIds ?? []).map((playerId) => Number(playerId));

                if (marked.length === 5) {
                    court = marked;
                }
            }

            return court;
        },

        undoSubstitutionOnCourt(court, line) {
            if (line.type !== 'substitution' || ! line.playerId || ! line.outgoingPlayerId) {
                return court;
            }

            const next = court.filter((id) => id !== Number(line.playerId));

            if (! next.includes(Number(line.outgoingPlayerId))) {
                next.push(Number(line.outgoingPlayerId));
            }

            return next;
        },

        openSubstitutionEdit(before, selected) {
            this.editingCourtBefore = before.map((id) => Number(id));
            this.courtSelection = selected.map((id) => Number(id));
            this.courtError = false;
        },

        foulCount(playerId) {
            const player = (this.baseline?.roster ?? []).find((rosterPlayer) => rosterPlayer.id === Number(playerId));
            const recorded = Number(player?.fouls ?? 0);
            const pending = this.items.filter((item) => item.operation === 'record'
                && item.payload.type === 'foul'
                && ! item.payload.forOpponent
                && Number(item.payload.playerId) === Number(playerId)).length;

            return recorded + pending;
        },

        foulMarks(playerId) {
            return Array.from({ length: this.foulCount(playerId) }, (_, index) => index);
        },

        isEditingShot() {
            return this.choosingShot
                && (this.editingPendingId !== null || this.editingEventId !== null);
        },

        shotType() {
            if (this.isEditingShot()) {
                return this.editingType;
            }

            return this.pendingType;
        },

        editNeedsNewShot(type, courtX, courtY) {
            if (! this.needsShot(type)) {
                return false;
            }

            if (courtX === null || courtX === undefined || courtY === null || courtY === undefined) {
                return true;
            }

            return ! this.allowsShot(type, Number(courtX), Number(courtY));
        },

        pickerTitle() {
            const type = this.shotType();

            return this.baseline?.pickerTitles?.[type]
                ?? this.baseline?.labels?.[type]
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

            const type = this.pendingType;
            const foulKind = type === 'foul' ? this.pendingFoulKind : null;
            const quarter = this.local.quarter;
            const clock = this.clock();
            this.closePicker();
            await this.enqueue('record', {
                type,
                playerId,
                forOpponent: false,
                foulKind,
                quarter,
                clock,
                courtX: null,
                courtY: null,
            });
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

            const type = this.pendingType;
            const foulKind = type === 'foul' ? this.pendingFoulKind : null;
            const quarter = this.local.quarter;
            const clock = this.clock();
            this.closePicker();
            await this.enqueue('record', {
                type,
                playerId: null,
                forOpponent: true,
                foulKind,
                quarter,
                clock,
                courtX: null,
                courtY: null,
            });
        },

        async recordCoach() {
            if (this.pendingType !== 'foul' || ! this.foulKindAllowsCoach()) {
                return;
            }

            const foulKind = this.pendingFoulKind;
            const quarter = this.local.quarter;
            const clock = this.clock();
            this.closePicker();
            await this.enqueue('record', {
                type: 'foul',
                playerId: null,
                forOpponent: false,
                foulKind,
                quarter,
                clock,
                courtX: null,
                courtY: null,
            });
        },

        async recordShot(courtX, courtY) {
            const type = this.shotType();

            if (! this.allowsShot(type, courtX, courtY)) {
                this.shotError = true;

                return false;
            }

            this.shotError = false;

            if (this.editingPendingId !== null) {
                await this.savePendingEdit({ courtX, courtY });

                return true;
            }

            if (this.editingEventId !== null) {
                this.editingCourtX = courtX;
                this.editingCourtY = courtY;
                await this.saveConfirmedEdit({ courtX, courtY });

                return true;
            }

            const playerId = this.shotForOpponent ? null : this.shotPlayerId;
            const forOpponent = this.shotForOpponent;
            const quarter = this.local.quarter;
            const clock = this.clock();
            this.closePicker();
            await this.enqueue('record', {
                type,
                playerId,
                forOpponent,
                foulKind: null,
                quarter,
                clock,
                courtX,
                courtY,
            });

            return true;
        },

        closePicker() {
            this.pendingType = null;
            this.pendingFoulKind = null;
            this.choosingPlayer = false;
            this.choosingShot = false;
            this.shotPlayerId = null;
            this.shotForOpponent = false;
        },

        cancelShot() {
            this.choosingShot = false;
            this.shotError = false;

            if (this.editingPendingId !== null || this.editingEventId !== null) {
                return;
            }

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

            const clock = this.local.awaitingQuarterStart
                ? this.openingClock()
                : this.clock();

            await this.enqueue('substitution', {
                playerIds: this.courtSelection.slice(),
                quarter: this.local.quarter,
                clock,
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
            // Newest user action, edits included. Server undo and cancel items are
            // bookkeeping and cannot be undone themselves.
            for (let index = this.items.length - 1; index >= 0; index -= 1) {
                const item = this.items[index];

                if (item.operation === 'undo' || item.operation === 'cancel') {
                    continue;
                }

                return item;
            }

            return null;
        },

        async undo() {
            if (! this.canUndo()) {
                return;
            }

            const item = this.removableItem();

            if (! item) {
                await this.enqueue('undo', {});

                return;
            }

            const cancelledSend = item.id === this.sendingId;

            if (cancelledSend) {
                this.drainEpoch += 1;
                this.draining = false;
                this.sendingId = null;
            }

            if (item.attempted || cancelledSend) {
                await this.replaceWithCancel(item);
            } else {
                await this.remove(item.id);
            }

            this.replay();
            this.clearSyncFailure();

            if (this.items.length > 0 && this.online) {
                this.releaseStuckDrainFlag();
                this.drain();
            }
        },

        isEditing() {
            return this.editingPendingId !== null || this.editingEventId !== null;
        },

        closeEdit() {
            this.editingPendingId = null;
            this.editingEventId = null;
            this.editingOriginalType = null;
            this.editingType = null;
            this.editingPlayerId = null;
            this.editingOutgoingPlayerId = null;
            this.editingForOpponent = false;
            this.editingFoulKind = null;
            this.editingEventClock = null;
            this.editingPlayerLines = [];
            this.editingCourtBefore = [];
            this.courtSelection = [];
            this.courtError = false;
            this.editingCourtX = null;
            this.editingCourtY = null;
            this.choosingShot = false;
            this.shotError = false;
        },

        async saveEdit() {
            if (this.editingPendingId !== null) {
                await this.savePendingEdit();

                return;
            }

            if (this.editingEventId !== null) {
                await this.saveConfirmedEdit();
            }
        },

        normalizeEditClock(value) {
            if (typeof value !== 'string') {
                return null;
            }

            const match = value.trim().match(/^(\d{1,2}):(\d{2})$/);

            if (! match) {
                return null;
            }

            const minutes = Number(match[1]);
            const seconds = Number(match[2]);

            if (minutes > 99 || seconds > 59) {
                return null;
            }

            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        },

        async saveConfirmedEdit(shot = null) {
            const eventId = this.editingEventId;
            const type = this.editingType;

            if (! eventId || ! type) {
                return;
            }

            const clock = this.normalizeEditClock(this.editingEventClock);

            if (! clock) {
                this.lastSyncError = 'invalid clock';

                return;
            }

            const originalType = this.editingOriginalType ?? type;
            const groups = this.baseline?.typeSwapGroups ?? [];
            const group = groups.find((options) => options.includes(originalType));

            if (type !== originalType && type !== 'timeout' && type !== 'substitution' && (! group || ! group.includes(type))) {
                return;
            }

            const forCoach = type === 'foul'
                && this.editingFoulKind === 'technical'
                && ! this.editingForOpponent
                && (this.editingPlayerId === null || this.editingPlayerId === undefined);

            if (type === 'substitution') {
                if (! this.editingPlayerId || ! this.editingOutgoingPlayerId) {
                    return;
                }
            } else if (type !== 'timeout' && ! this.editingForOpponent && ! forCoach && ! this.editingPlayerId) {
                return;
            }

            const courtX = shot?.courtX ?? this.editingCourtX ?? null;
            const courtY = shot?.courtY ?? this.editingCourtY ?? null;

            if (this.editNeedsNewShot(type, courtX, courtY)) {
                this.shotError = false;
                this.choosingShot = true;

                return;
            }

            const forOpponent = Boolean(this.editingForOpponent);
            const playerId = type === 'substitution'
                ? this.editingPlayerId
                : (forOpponent || forCoach ? null : this.editingPlayerId);
            const needsShot = this.needsShot(type);
            const payload = {
                eventId: Number(eventId),
                type,
                playerId,
                outgoingPlayerId: type === 'substitution' ? this.editingOutgoingPlayerId : null,
                forOpponent,
                foulKind: type === 'foul' ? (this.editingFoulKind ?? null) : null,
                clock,
                courtX: needsShot ? courtX : null,
                courtY: needsShot ? courtY : null,
            };
            const previous = this.committedLines.find((line) => Number(line.id) === Number(eventId));
            this.closeEdit();
            await this.enqueue('edit', payload, previous ? { revert: this.revertFor(previous) } : {});
        },

        revertFor(line) {
            const type = line.type ?? line.actionKey;
            const forOpponent = Boolean(line.forOpponent);

            return {
                eventId: Number(line.id),
                type,
                playerId: forOpponent ? null : (line.playerId ?? null),
                outgoingPlayerId: type === 'substitution' ? (line.outgoingPlayerId ?? null) : null,
                forOpponent,
                foulKind: type === 'foul' ? (line.foulKind ?? null) : null,
                clock: line.clock,
                courtX: line.courtX ?? null,
                courtY: line.courtY ?? null,
            };
        },

        editedLine(current, payload) {
            const forOpponent = Boolean(payload.forOpponent);
            const homeAway = this.baseline?.homeAway;
            let player = this.baseline?.opponentName ?? '';
            let playerKeys = ['opponent'];

            let playerLines = [];

            if (payload.type === 'substitution') {
                const roster = this.baseline?.roster ?? [];
                const incoming = roster.find((entry) => entry.id === Number(payload.playerId));
                const outgoing = roster.find((entry) => entry.id === Number(payload.outgoingPlayerId));
                player = '';
                playerKeys = [];

                if (incoming) {
                    playerLines.push({ enters: true, name: incoming.label ?? incoming.name });
                    playerKeys.push(`player:${incoming.id}`);
                }

                if (outgoing) {
                    playerLines.push({ enters: false, name: outgoing.label ?? outgoing.name });
                    playerKeys.push(`player:${outgoing.id}`);
                }
            } else if (payload.type === 'timeout') {
                player = forOpponent ? (this.baseline?.opponentName ?? '') : (this.baseline?.teamName ?? '');
                playerKeys = forOpponent ? ['opponent'] : ['team'];
            } else if (! forOpponent) {
                if (payload.playerId) {
                    const rosterPlayer = (this.baseline?.roster ?? []).find((entry) => entry.id === Number(payload.playerId));
                    player = rosterPlayer?.label ?? rosterPlayer?.name ?? '';
                    playerKeys = [`player:${Number(payload.playerId)}`];
                } else if (payload.type === 'foul') {
                    player = this.baseline?.coachBenchLabel ?? 'Coach / Bench';
                    playerKeys = ['coach'];
                } else {
                    player = this.baseline?.teamName ?? '';
                    playerKeys = ['team'];
                }
            }

            const label = payload.type === 'foul'
                ? this.foulKindLabel(payload.foulKind ?? null)
                : (this.baseline?.labels?.[payload.type] ?? payload.type);

            return {
                ...current,
                clock: payload.clock ?? current.clock,
                type: payload.type,
                actionKey: payload.type,
                playerId: payload.playerId,
                outgoingPlayerId: payload.outgoingPlayerId,
                forOpponent,
                foulKind: payload.foulKind,
                courtX: Object.prototype.hasOwnProperty.call(payload, 'courtX') ? payload.courtX : current.courtX,
                courtY: Object.prototype.hasOwnProperty.call(payload, 'courtY') ? payload.courtY : current.courtY,
                player,
                playerKeys,
                label,
                missed: String(payload.type).includes('missed'),
                side: payload.type === 'substitution'
                    ? current.side
                    : (forOpponent ? oppositeSide(homeAway) : homeAway),
                playerLines,
            };
        },

        // The server only accepts a player who was on the court when the action happened.
        editRoster() {
            const roster = this.baseline?.roster ?? [];
            let court = (this.baseline?.court ?? []).map((id) => Number(id));

            if (this.editingEventId !== null) {
                const lines = this.serverLines ?? [];
                const index = lines.findIndex((line) => Number(line.id) === Number(this.editingEventId));

                if (index < 0) {
                    return roster;
                }

                // Lines are newest first: walk back over later substitutions.
                lines.slice(0, index).forEach((line) => {
                    if (line.type !== 'substitution' || ! line.playerId || ! line.outgoingPlayerId) {
                        return;
                    }

                    court = court.filter((id) => id !== Number(line.playerId));

                    if (! court.includes(Number(line.outgoingPlayerId))) {
                        court.push(Number(line.outgoingPlayerId));
                    }
                });

                return roster.filter((player) => court.includes(Number(player.id)));
            }

            if (this.editingPendingId === null) {
                return roster;
            }

            for (const item of this.items) {
                if (item.id === this.editingPendingId) {
                    break;
                }

                if (item.operation === 'substitution') {
                    court = (item.payload.playerIds ?? []).map((id) => Number(id));
                }
            }

            return roster.filter((player) => court.includes(Number(player.id)));
        },

        editableTypesForEdit() {
            const current = this.editingOriginalType ?? this.editingType;
            const groups = this.baseline?.typeSwapGroups ?? [];
            const group = groups.find((options) => options.includes(current));

            if (! group || group.length <= 1) {
                return [];
            }

            return (this.baseline?.editableTypes ?? []).filter((stat) => group.includes(stat.value));
        },

        editPending(id) {
            const item = this.items.find((queued) => queued.id === id);

            if (! item || item.id === this.sendingId) {
                return;
            }

            if (item.operation === 'substitution') {
                this.editingEventId = null;
                this.editingPendingId = id;
                this.editingOriginalType = 'substitution';
                this.editingType = 'substitution';
                this.editingPlayerId = null;
                this.editingOutgoingPlayerId = null;
                this.editingForOpponent = false;
                this.editingFoulKind = null;
                this.editingEventClock = item.payload.clock;
                this.editingPlayerLines = [];
                this.editingCourtX = null;
                this.editingCourtY = null;
                this.choosingShot = false;
                this.shotError = false;
                this.openSubstitutionEdit(this.courtBeforeQueuedItem(id), item.payload.playerIds ?? []);

                return;
            }

            if (item.operation !== 'record') {
                return;
            }

            this.editingEventId = null;
            this.editingPendingId = id;
            this.editingOriginalType = item.payload.type;
            this.editingType = item.payload.type;
            this.editingPlayerId = item.payload.playerId;
            this.editingOutgoingPlayerId = null;
            this.editingForOpponent = Boolean(item.payload.forOpponent);
            this.editingFoulKind = item.payload.foulKind ?? null;
            this.editingEventClock = item.payload.clock;
            this.editingPlayerLines = [];
            this.editingCourtX = item.payload.courtX ?? null;
            this.editingCourtY = item.payload.courtY ?? null;
            this.choosingShot = false;
            this.shotError = false;
        },

        editCommitted(line) {
            if (! line?.editable) {
                return;
            }

            this.editingPendingId = null;
            this.editingEventId = line.id;
            this.editingOriginalType = line.type ?? line.actionKey;
            this.editingType = line.type ?? line.actionKey;
            this.editingPlayerId = line.playerId ?? null;
            this.editingOutgoingPlayerId = line.outgoingPlayerId ?? null;
            this.editingForOpponent = Boolean(line.forOpponent);
            this.editingFoulKind = line.foulKind ?? null;
            this.editingEventClock = line.clock;
            this.editingPlayerLines = line.playerLines ?? [];
            this.editingCourtX = line.courtX ?? null;
            this.editingCourtY = line.courtY ?? null;
            this.choosingShot = false;
            this.shotError = false;

            if ((line.type ?? line.actionKey) === 'substitution') {
                const before = this.courtBeforeCommittedLine(line.id);
                const outgoing = Number(line.outgoingPlayerId);
                const incoming = Number(line.playerId);
                const selected = before.filter((id) => id !== outgoing);

                if (incoming && ! selected.includes(incoming)) {
                    selected.push(incoming);
                }

                this.editingPlayerId = incoming || null;
                this.editingOutgoingPlayerId = outgoing || null;
                this.openSubstitutionEdit(before, selected);
            }
        },

        setEditingFoulKind(kind) {
            if (this.editingType !== 'foul') {
                return;
            }

            this.editingFoulKind = kind || null;

            if (this.editingFoulKind !== 'technical' && this.editingPlayerId === null && ! this.editingForOpponent) {
                this.editingPlayerId = null;
            }
        },

        editingFoulAllowsCoach() {
            return this.editingType === 'foul' && this.editingFoulKind === 'technical';
        },

        async savePendingEdit(shot = null) {
            const item = this.items.find((queued) => queued.id === this.editingPendingId);

            if (! item || item.id === this.sendingId) {
                return;
            }

            if (item.operation === 'substitution') {
                await this.savePendingSubstitution(item);

                return;
            }

            const clock = this.normalizeEditClock(this.editingEventClock ?? item.payload.clock);

            if (! clock) {
                this.lastSyncError = 'invalid clock';

                return;
            }

            const type = this.editingType;
            const originalType = this.editingOriginalType ?? item.payload.type;
            const groups = this.baseline?.typeSwapGroups ?? [];
            const group = groups.find((options) => options.includes(originalType));

            if (type !== originalType && (! group || ! group.includes(type))) {
                return;
            }

            const forCoach = type === 'foul'
                && this.editingFoulKind === 'technical'
                && ! this.editingForOpponent
                && (this.editingPlayerId === null || this.editingPlayerId === undefined);

            if (type !== 'timeout' && type !== 'substitution' && ! this.editingForOpponent && ! forCoach && ! this.editingPlayerId) {
                return;
            }

            const courtX = shot?.courtX ?? this.editingCourtX ?? item.payload.courtX ?? null;
            const courtY = shot?.courtY ?? this.editingCourtY ?? item.payload.courtY ?? null;
            const needsShot = this.needsShot(type);

            // 3P↔2P keeps old coordinates; server rejects mismatched zones and blocks the queue.
            if (this.editNeedsNewShot(type, courtX, courtY)) {
                this.shotError = false;
                this.choosingShot = true;

                return;
            }

            const payload = toPlain({
                ...item.payload,
                type,
                playerId: this.editingForOpponent || forCoach ? null : this.editingPlayerId,
                forOpponent: this.editingForOpponent,
                foulKind: type === 'foul' ? this.editingFoulKind : null,
                clock,
                courtX: needsShot ? courtX : null,
                courtY: needsShot ? courtY : null,
            }) ?? {};

            if (item.attempted) {
                // The server ignores a reused id, so a sent record is cancelled and
                // re-recorded in the same queue position instead of changed in place.
                const index = this.items.findIndex((queued) => queued.id === item.id);
                const nextSeq = this.items[index + 1]?.seq ?? item.seq + 1;
                const cancel = this.cancelFor(item);
                const record = {
                    id: crypto.randomUUID(),
                    gameId: item.gameId,
                    seq: (item.seq + nextSeq) / 2,
                    operation: 'record',
                    payload,
                };
                this.items = [...this.items.slice(0, index), cancel, record, ...this.items.slice(index + 1)];
                await putItem(this.database, durableQueueItem(cancel));
                await putItem(this.database, durableQueueItem(record));
                await deleteItem(this.database, item.id);
            } else {
                const updated = { ...durableQueueItem(item), payload };
                this.items = this.items.map((queued) => (queued.id === updated.id ? updated : queued));
                await putItem(this.database, durableQueueItem(updated));
            }

            this.choosingShot = false;
            this.shotError = false;
            this.replay();
            this.closeEdit();
            this.clearSyncFailure();
            this.releaseStuckDrainFlag();
            this.drain();
        },

        async savePendingSubstitution(item) {
            const clock = this.normalizeEditClock(this.editingEventClock ?? item.payload.clock);
            const selected = [...new Set(this.courtSelection.map((id) => Number(id)))];

            if (! clock) {
                this.lastSyncError = 'invalid clock';

                return;
            }

            if (selected.length !== 5) {
                this.courtError = true;

                return;
            }

            const payload = toPlain({
                ...item.payload,
                playerIds: selected,
                clock,
            }) ?? {};

            if (item.attempted) {
                const index = this.items.findIndex((queued) => queued.id === item.id);
                const nextSeq = this.items[index + 1]?.seq ?? item.seq + 1;
                const cancel = this.cancelFor(item);
                const replacement = {
                    id: crypto.randomUUID(),
                    gameId: item.gameId,
                    seq: (item.seq + nextSeq) / 2,
                    operation: 'substitution',
                    payload,
                };
                this.items = [...this.items.slice(0, index), cancel, replacement, ...this.items.slice(index + 1)];
                await putItem(this.database, durableQueueItem(cancel));
                await putItem(this.database, durableQueueItem(replacement));
                await deleteItem(this.database, item.id);
            } else {
                const updated = { ...durableQueueItem(item), payload };
                this.items = this.items.map((queued) => (queued.id === updated.id ? updated : queued));
                await putItem(this.database, durableQueueItem(updated));
            }

            this.replay();
            this.closeEdit();
            this.clearSyncFailure();
            this.releaseStuckDrainFlag();
            this.drain();
        },

        finishGame() {
            if (this.items.length > 0) {
                this.finishWhenEmpty = true;
                this.releaseStuckDrainFlag();
                this.drain();

                return;
            }

            this.wire.finishGameAndExit();
        },

        onCourt(playerId) {
            return (this.local?.court ?? []).includes(playerId);
        },

        recordLine(item, teamPoints, opponentPoints) {
            const ours = ! item.payload.forOpponent;
            let player = this.baseline.opponentName;
            let playerKeys = ['opponent'];

            if (ours) {
                if (item.payload.playerId) {
                    player = this.baseline.roster.find((rosterPlayer) => rosterPlayer.id === Number(item.payload.playerId))?.label ?? '';
                    playerKeys = [`player:${Number(item.payload.playerId)}`];
                } else if (item.payload.type === 'foul') {
                    player = this.baseline.coachBenchLabel ?? 'Coach / Bench';
                    playerKeys = ['coach'];
                } else {
                    player = this.baseline.teamName ?? '';
                    playerKeys = ['team'];
                }
            }

            const label = item.payload.type === 'foul'
                ? this.foulKindLabel(item.payload.foulKind ?? null)
                : (this.baseline.labels[item.payload.type] ?? item.payload.type);

            return {
                id: item.id,
                clock: item.payload.clock,
                score: runningScoreLabel(this.baseline.homeAway, teamPoints, opponentPoints),
                player,
                playerLines: [],
                label,
                side: ours ? this.baseline.homeAway : oppositeSide(this.baseline.homeAway),
                editable: true,
                missed: String(item.payload.type).includes('missed'),
                actionKey: item.payload.type,
                playerKeys,
            };
        },

        substitutionLine(item, court, teamPoints, opponentPoints) {
            const next = new Set((item.payload.playerIds ?? []).map((id) => Number(id)));
            const previous = new Set(court);
            const entering = this.baseline.roster.filter((player) => next.has(player.id) && ! previous.has(player.id));
            const leaving = this.baseline.roster.filter((player) => previous.has(player.id) && ! next.has(player.id));

            return {
                id: item.id,
                clock: item.payload.clock,
                score: runningScoreLabel(this.baseline.homeAway, teamPoints, opponentPoints),
                player: '',
                playerLines: [
                    ...entering.map((player) => ({ enters: true, name: player.label })),
                    ...leaving.map((player) => ({ enters: false, name: player.label })),
                ],
                label: this.baseline.labels.substitution ?? 'sub',
                side: this.baseline.homeAway,
                editable: true,
                missed: false,
                actionKey: 'substitution',
                playerKeys: [...entering, ...leaving].map((player) => `player:${player.id}`),
            };
        },

        timeoutLine(item, teamPoints, opponentPoints) {
            const ours = item.payload.side === this.baseline.homeAway;

            return {
                id: item.id,
                clock: item.payload.clock,
                score: runningScoreLabel(this.baseline.homeAway, teamPoints, opponentPoints),
                player: ours ? this.baseline.teamName : this.baseline.opponentName,
                playerLines: [],
                label: this.baseline.labels.timeout ?? 'timeout',
                side: item.payload.side,
                editable: false,
                missed: false,
                actionKey: 'timeout',
                playerKeys: ours ? ['team'] : ['opponent'],
            };
        },

        periodLine(item, teamPoints, opponentPoints) {
            const started = item.payload.type === 'quarter_started';

            return {
                id: item.id,
                clock: item.payload.clock,
                score: runningScoreLabel(this.baseline.homeAway, teamPoints, opponentPoints),
                player: '',
                playerLines: [],
                label: this.baseline.labels[item.payload.type] ?? (started ? 'period start' : 'period end'),
                side: null,
                editable: false,
                missed: false,
                actionKey: item.payload.type,
                playerKeys: [],
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
        queue.clearSyncFailure();
        queue.reattachWire();
        queue.drain();
    });

    window.addEventListener('offline', () => {
        const queue = window.Alpine.store('collectQueue');

        if (! queue) {
            return;
        }

        queue.online = false;
        queue.clearRetryTimer();
    });
}

document.addEventListener('alpine:init', registerCollectQueue);

if (window.Alpine) {
    registerCollectQueue();
}

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            requestAnimationFrame(() => {
                window.Alpine.store('collectQueue')?.refreshBaseline();
            });
        });
    });
});
