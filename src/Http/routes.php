<?php

Route::group([
    'namespace' => 'Raikia\SeatSpyHunter\Http\Controllers',
    'prefix' => 'seat-spy-hunter',
    'middleware' => ['web', 'auth', 'can:seat-spy-hunter.view'],
], function () {
    Route::get('/', [
        'as' => 'seat-spy-hunter.index',
        'uses' => 'IntelDashboardController@index',
    ]);

    Route::get('/characters/{report}', [
        'as' => 'seat-spy-hunter.characters.show',
        'uses' => 'IntelDashboardController@show',
    ]);

    Route::post('/refresh', [
        'as' => 'seat-spy-hunter.refresh',
        'uses' => 'IntelDashboardController@refresh',
    ]);

    Route::post('/reports/{report}/review', [
        'as' => 'seat-spy-hunter.reports.review',
        'uses' => 'IntelDashboardController@updateReview',
    ]);

    Route::post('/reports/{report}/suppressions', [
        'as' => 'seat-spy-hunter.reports.suppressions.store',
        'uses' => 'IntelDashboardController@storeSuppression',
    ]);

    Route::delete('/suppressions/{suppression}', [
        'as' => 'seat-spy-hunter.suppressions.destroy',
        'uses' => 'IntelDashboardController@destroySuppression',
    ]);

    Route::group(['middleware' => 'can:seat-spy-hunter.settings'], function () {
        Route::get('/settings', [
            'as' => 'seat-spy-hunter.settings',
            'uses' => 'IntelSettingsController@index',
        ]);
        Route::get('/caches', [
            'as' => 'seat-spy-hunter.caches',
            'uses' => 'IntelCacheController@index',
        ]);
        Route::delete('/caches/ip/{record}', [
            'as' => 'seat-spy-hunter.caches.ip.destroy',
            'uses' => 'IntelCacheController@destroyIp',
        ]);
        Route::delete('/caches/evewho/{member}', [
            'as' => 'seat-spy-hunter.caches.evewho.destroy',
            'uses' => 'IntelCacheController@destroyEveWhoMember',
        ]);
        Route::post('/caches/evewho/refresh-esi', [
            'as' => 'seat-spy-hunter.caches.evewho.refresh-esi',
            'uses' => 'IntelCacheController@refreshEveWhoMemberEsi',
        ]);
        Route::post('/settings/general', [
            'as' => 'seat-spy-hunter.settings.general',
            'uses' => 'IntelSettingsController@updateGeneral',
        ]);
        Route::get('/settings/search/corporations', [
            'as' => 'seat-spy-hunter.settings.search.corporations',
            'uses' => 'IntelSettingsController@searchCorporations',
        ]);
        Route::get('/settings/search/alliances', [
            'as' => 'seat-spy-hunter.settings.search.alliances',
            'uses' => 'IntelSettingsController@searchAlliances',
        ]);
        Route::get('/settings/search/entities', [
            'as' => 'seat-spy-hunter.settings.search.entities',
            'uses' => 'IntelSettingsController@searchEntities',
        ]);
        Route::post('/settings/entities', [
            'as' => 'seat-spy-hunter.settings.entities.store',
            'uses' => 'IntelSettingsController@storeEntity',
        ]);
        Route::delete('/settings/entities/{entity}', [
            'as' => 'seat-spy-hunter.settings.entities.destroy',
            'uses' => 'IntelSettingsController@destroyEntity',
        ]);
        Route::post('/settings/ignored-characters', [
            'as' => 'seat-spy-hunter.settings.ignored-characters.store',
            'uses' => 'IntelSettingsController@storeIgnoredCharacter',
        ]);
        Route::delete('/settings/ignored-characters/{character}', [
            'as' => 'seat-spy-hunter.settings.ignored-characters.destroy',
            'uses' => 'IntelSettingsController@destroyIgnoredCharacter',
        ]);
        Route::post('/settings/ip-intelligence', [
            'as' => 'seat-spy-hunter.settings.ip-intelligence.store',
            'uses' => 'IntelSettingsController@storeIpIntelligence',
        ]);
    });
});
