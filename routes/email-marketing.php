<?php

use Illuminate\Support\Facades\Route;
use Dinara\EmailMarketing\Controllers\EmailMarketingController;

/*
|--------------------------------------------------------------------------
| Email Marketing Routes
|--------------------------------------------------------------------------
|
| These routes handle email marketing functionality including campaigns,
| templates, SMTP settings, and email tracking.
|
*/

// Public tracking routes (no auth required)
Route::get('/email/track/{id}', [EmailMarketingController::class, 'trackOpen'])
    ->name('email-marketing.track');

Route::get('/email/click/{id}', [EmailMarketingController::class, 'trackClick'])
    ->name('email-marketing.click');

// Unsubscribe routes (no auth required)
Route::get('/email/unsubscribe', [EmailMarketingController::class, 'showUnsubscribe'])
    ->name('email-marketing.unsubscribe');

Route::post('/email/unsubscribe', [EmailMarketingController::class, 'processUnsubscribe'])
    ->name('email-marketing.unsubscribe.process');

// Admin routes with configurable middleware
Route::prefix(config('email-marketing.route_prefix', 'admin/email-marketing'))
    ->middleware(config('email-marketing.middleware', ['web', 'auth']))
    ->name('email-marketing.')
    ->group(function () {

        // Dashboard
        Route::get('/', [EmailMarketingController::class, 'index'])->name('index');

        // SMTP Settings
        Route::get('/smtp', [EmailMarketingController::class, 'smtpSettings'])->name('smtp');
        Route::post('/smtp', [EmailMarketingController::class, 'saveSmtpSettings'])->name('smtp.save');
        Route::post('/smtp/test', [EmailMarketingController::class, 'testSmtp'])->name('smtp.test');

        // Templates
        Route::get('/templates', [EmailMarketingController::class, 'templates'])->name('templates');
        Route::get('/templates/create', [EmailMarketingController::class, 'createTemplate'])->name('templates.create');
        Route::post('/templates', [EmailMarketingController::class, 'storeTemplate'])->name('templates.store');
        Route::get('/templates/{template}/edit', [EmailMarketingController::class, 'editTemplate'])->name('templates.edit');
        Route::put('/templates/{template}', [EmailMarketingController::class, 'updateTemplate'])->name('templates.update');
        Route::delete('/templates/{template}', [EmailMarketingController::class, 'deleteTemplate'])->name('templates.delete');
        Route::get('/templates/{template}/preview', [EmailMarketingController::class, 'previewTemplate'])->name('templates.preview');
        Route::post('/templates/test-email', [EmailMarketingController::class, 'sendTestEmail'])->name('templates.test');

        // Campaigns
        Route::get('/campaigns', [EmailMarketingController::class, 'campaigns'])->name('campaigns');
        Route::get('/campaigns/create', [EmailMarketingController::class, 'createCampaign'])->name('campaigns.create');
        Route::post('/campaigns', [EmailMarketingController::class, 'storeCampaign'])->name('campaigns.store');
        Route::get('/campaigns/{campaign}', [EmailMarketingController::class, 'showCampaign'])->name('campaigns.show');
        Route::post('/campaigns/{campaign}/start', [EmailMarketingController::class, 'startCampaign'])->name('campaigns.start');
        Route::post('/campaigns/{campaign}/pause', [EmailMarketingController::class, 'pauseCampaign'])->name('campaigns.pause');
        Route::post('/campaigns/{campaign}/resume', [EmailMarketingController::class, 'resumeCampaign'])->name('campaigns.resume');
        Route::delete('/campaigns/{campaign}', [EmailMarketingController::class, 'deleteCampaign'])->name('campaigns.delete');

        // Lead/Hotel Search API
        Route::get('/hotels/search', [EmailMarketingController::class, 'searchHotels'])->name('hotels.search');

        // Image Upload
        Route::get('/images', [EmailMarketingController::class, 'images'])->name('images');
        Route::post('/images/upload', [EmailMarketingController::class, 'uploadImage'])->name('images.upload');
        Route::delete('/images/{image}', [EmailMarketingController::class, 'deleteImage'])->name('images.delete');

        // Unsubscribes
        Route::get('/unsubscribes', [EmailMarketingController::class, 'unsubscribes'])->name('unsubscribes');
        Route::delete('/unsubscribes/{unsubscribe}', [EmailMarketingController::class, 'deleteUnsubscribe'])->name('unsubscribes.delete');
    });
