<?php

use Illuminate\Support\Facades\Route;
use Stumason\Coolify\Http\Middleware\Authenticate;

Route::middleware(Authenticate::class)->group(function () {
    // API Routes (JSON responses for AJAX calls)
    Route::prefix('api')->group(function () {
        // Dashboard stats
        Route::get('/stats', 'DashboardStatsController@index')->name('coolify.stats');

        // Application routes
        Route::get('/applications/{uuid}', 'ApplicationController@show')->name('coolify.applications.show');
        Route::post('/applications/{uuid}/deploy', 'ApplicationController@deploy')->name('coolify.applications.deploy');
        Route::post('/applications/{uuid}/restart', 'ApplicationController@restart')->name('coolify.applications.restart');
        Route::post('/applications/{uuid}/stop', 'ApplicationController@stop')->name('coolify.applications.stop');
        Route::post('/applications/{uuid}/start', 'ApplicationController@start')->name('coolify.applications.start');
        Route::get('/applications/{uuid}/logs', 'ApplicationController@logs')->name('coolify.applications.logs');
        Route::get('/applications/{uuid}/envs', 'ApplicationController@envs')->name('coolify.applications.envs');
        Route::post('/applications/{uuid}/envs', 'ApplicationController@createEnv')->name('coolify.applications.envs.create');
        Route::patch('/applications/{uuid}/envs/{envUuid}', 'ApplicationController@updateEnv')->name('coolify.applications.envs.update');
        Route::delete('/applications/{uuid}/envs/{envUuid}', 'ApplicationController@deleteEnv')->name('coolify.applications.envs.delete');

        // Deployment routes
        Route::get('/applications/{applicationUuid}/deployments', 'DeploymentController@index')->name('coolify.deployments.index');
        Route::get('/deployments/{uuid}', 'DeploymentController@show')->name('coolify.deployments.show');
        Route::get('/deployments/{uuid}/logs', 'DeploymentController@logs')->name('coolify.deployments.logs');
        Route::post('/deployments/{uuid}/cancel', 'DeploymentController@cancel')->name('coolify.deployments.cancel');

        // Database routes
        Route::get('/databases', 'DatabaseController@index')->name('coolify.databases.index');
        Route::get('/databases/{uuid}', 'DatabaseController@show')->name('coolify.databases.show');
        Route::post('/databases/{uuid}/start', 'DatabaseController@start')->name('coolify.databases.start');
        Route::post('/databases/{uuid}/stop', 'DatabaseController@stop')->name('coolify.databases.stop');
        Route::post('/databases/{uuid}/restart', 'DatabaseController@restart')->name('coolify.databases.restart');
        Route::get('/databases/{uuid}/backups', 'DatabaseController@backups')->name('coolify.databases.backups');

        // Server routes
        Route::get('/servers', 'ServerController@index')->name('coolify.servers.index');
        Route::get('/servers/{uuid}', 'ServerController@show')->name('coolify.servers.show');
        Route::get('/servers/{uuid}/resources', 'ServerController@resources')->name('coolify.servers.resources');
        Route::get('/servers/{uuid}/domains', 'ServerController@domains')->name('coolify.servers.domains');
        Route::post('/servers/{uuid}/validate', 'ServerController@validate')->name('coolify.servers.validate');

        // Service routes
        Route::get('/services', 'ServiceController@index')->name('coolify.services.index');
        Route::get('/services/{uuid}', 'ServiceController@show')->name('coolify.services.show');
        Route::post('/services/{uuid}/start', 'ServiceController@start')->name('coolify.services.start');
        Route::post('/services/{uuid}/stop', 'ServiceController@stop')->name('coolify.services.stop');
        Route::post('/services/{uuid}/restart', 'ServiceController@restart')->name('coolify.services.restart');

        // Project routes
        Route::get('/projects', 'ProjectController@index')->name('coolify.projects.index');
        Route::get('/projects/{uuid}', 'ProjectController@show')->name('coolify.projects.show');
        Route::get('/projects/{uuid}/environments', 'ProjectController@environments')->name('coolify.projects.environments');

        // Team routes
        Route::get('/teams', 'TeamController@index')->name('coolify.teams.index');
        Route::get('/teams/current', 'TeamController@current')->name('coolify.teams.current');
        Route::get('/teams/current/members', 'TeamController@members')->name('coolify.teams.members');

        // Environment routes (configured resources)
        Route::get('/environments', 'EnvironmentController@index')->name('coolify.environments.index');
        Route::post('/environments/{id}/switch', 'EnvironmentController@switch')->name('coolify.environments.switch');
    });

    // SPA catch-all - serves Vue app for all dashboard routes
    Route::get('/{any?}', 'DashboardController@index')
        ->where('any', '^(?!api).*$')
        ->name('coolify.index');
});
