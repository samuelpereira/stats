<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prune missing FPB records
    |--------------------------------------------------------------------------
    |
    | When true, importing games or roster removes local rows that still have
    | an FPB id but no longer appear on the team page. Keep false to only
    | create and update.
    |
    */

    'prune_missing' => env('FPB_PRUNE_MISSING', false),

    /*
    |--------------------------------------------------------------------------
    | Game sheet URL
    |--------------------------------------------------------------------------
    |
    | Used when fetching a single game by FPB id. Include {id} where the
    | internal game id should be substituted.
    |
    */

    'game_sheet_url' => env('FPB_GAME_SHEET_URL', 'https://www.fpb.pt/ficha-de-jogo?internalID={id}'),

];
