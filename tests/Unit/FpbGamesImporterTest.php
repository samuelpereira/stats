<?php

use App\Services\Fpb\FpbGamesImporter;

test('fpb parse extracts fixtures from team page html', function () {
    $html = <<<'HTML'
<h3 class="date">15 OUT 2025</h3>
<a href="/ficha-de-jogo?internalID=99901">
<div class="image-container">
<img alt="Logo Test Club Alpha" src="/uploads/clubes/logotipo/alpha.jpg">
</div>
<span class="fullName">Test Club Alpha</span>
<div class="image-container">
<img alt="Logo Test Club Beta" src="https://logos.test/beta.jpg">
</div>
<span class="fullName">Test Club Beta</span>
<div class="results_text">72</div>
<div class="results_text">68</div>
<div class="hour"><h3>20H30</h3></div>
<div class="location-wrapper"><b>Example City, Arena</b></div>
<span class="competition"><span>1ª Divisão | Jornada 3</span></span>
HTML;

    $fixtures = app(FpbGamesImporter::class)->parse($html);

    expect($fixtures)->toHaveCount(1)
        ->and($fixtures[0]['id'])->toBe('99901')
        ->and($fixtures[0]['home'])->toBe('Test Club Alpha')
        ->and($fixtures[0]['away'])->toBe('Test Club Beta')
        ->and($fixtures[0]['pc'])->toBe(72)
        ->and($fixtures[0]['pf'])->toBe(68)
        ->and($fixtures[0]['loc'])->toBe('Example City, Arena')
        ->and($fixtures[0]['comp'])->toBe('Jornada 3')
        ->and($fixtures[0]['home_logo'])->toBe('https://www.fpb.pt/uploads/clubes/logotipo/alpha.jpg')
        ->and($fixtures[0]['away_logo'])->toBe('https://logos.test/beta.jpg')
        ->and($fixtures[0]['date_time']->format('Y-m-d H:i'))->toBe('2025-10-15 20:30');
});

test('fpb parse strips leaked php from competition labels', function () {
    $html = <<<'HTML'
<h3 class="date">10 JAN 2027</h3>
<a href="/ficha-de-jogo?internalID=99902">
<span class="fullName">GD Gafanha/ Grupeixe</span>
<span class="fullName">Sub-18 Femininos</span>
<div class="hour"><h3>16H30</h3></div>
<span class="competition"><span><?//=$_SESSION['competicoes'][$game['competition_id']]['escalao'].' '.$_SESSION['competicoes'][$game['competition_id']]['genero']?> Campeonato Distrital Sub18 Femininos</span></span>
HTML;

    $fixtures = app(FpbGamesImporter::class)->parse($html);

    expect($fixtures)->toHaveCount(1)
        ->and($fixtures[0]['comp'])->toBe('Campeonato Distrital Sub18 Femininos');
});

test('fpb parse game sheet extracts match details', function () {
    $html = <<<'HTML'
<title>União da Madeira vs. Académico FC | VI Campeonato da Liga Feminina</title>
<section class="match-highlights">
<div class="match-wrapper">
<p class="phase">Fase regular 13</p>
<p class="date">11 JAN 2004</p>
<div class="match-inner-wrapper">
<div class="team home">
<p class="bigName" style="color:#E32C23">União da Madeira </p>
<img alt="Home Team Logo" src=https://logos.test/home.png>
</div>
<div class="points">
<span style="color:#E32C23">61</span>
<div class="dash"></div><span style="color:#ACABAB">72</span>
</div>
<div class="team away">
<img alt="Away Team Logo" src="https://logos.test/away.png">
<p class="bigName" style="color:#ACABAB">Académico FC </p>
</div>
</div>
<p class="location"><a href="/recinto/9438"> Pavilhão Ponta do Sol </a></p>
</div>
</section>
HTML;

    $sheet = app(FpbGamesImporter::class)->parseGameSheet($html);

    expect($sheet)->not->toBeNull()
        ->and($sheet['home'])->toBe('União da Madeira')
        ->and($sheet['away'])->toBe('Académico FC')
        ->and($sheet['home_points'])->toBe(61)
        ->and($sheet['away_points'])->toBe(72)
        ->and($sheet['place'])->toBe('Pavilhão Ponta do Sol')
        ->and($sheet['competition'])->toBe('VI Campeonato da Liga Feminina')
        ->and($sheet['journey'])->toBe('Fase regular 13')
        ->and($sheet['home_logo'])->toBe('https://logos.test/home.png')
        ->and($sheet['away_logo'])->toBe('https://logos.test/away.png')
        ->and($sheet['date_time']->format('Y-m-d'))->toBe('2004-01-11');
});

test('fpb parse players extracts roster from team page html', function () {
    $html = <<<'HTML'
<div class="roster__player">
<a href="/atletas/4242"></a>
<h2 class="header__heading">Sample Athlete</h2>
<div class="header_position">Base</div>
<div class="header_jnumber">7</div>
</div>
HTML;

    $athletes = app(FpbGamesImporter::class)->parsePlayers($html);

    expect($athletes)->toHaveCount(1)
        ->and($athletes[0]['fpb_id'])->toBe('4242')
        ->and($athletes[0]['name'])->toBe('Sample Athlete')
        ->and($athletes[0]['position'])->toBe('Base')
        ->and($athletes[0]['number'])->toBe('7');
});
