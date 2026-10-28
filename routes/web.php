<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VesselController;
use App\Http\Controllers\Admin\VesselTypeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\FundRequestController;
use App\Http\Controllers\FundRequestWorkflowController;
use App\Http\Controllers\Admin\DocumentTypeController;
use App\Http\Controllers\Admin\NatureOfCostController;
use App\Http\Controllers\LiquidationController;
use App\Http\Controllers\LiquidationItemController;
use App\Http\Controllers\LiquidationWorkflowController;
use App\Http\Controllers\QuestionableExpenseController;
use App\Http\Controllers\RevolvingFundController;
use App\Http\Controllers\RevolvingFundExpenseController;
use App\Http\Controllers\RevolvingFundExpenseItemController;
use App\Http\Controllers\RevolvingFundExpenseWorkflowController;
use App\Http\Controllers\RevolvingFundVoyageController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', fn () => redirect()->route('fund-requests.index'));

    // Fund requests (Process A)
    Route::resource('fund-requests', FundRequestController::class)
        ->except(['destroy']);

    Route::prefix('fund-requests/{fund_request}')->name('fund-requests.')->group(function () {
        Route::post('submit', [FundRequestWorkflowController::class, 'submit'])->name('submit');
        Route::post('validate-oe', [FundRequestWorkflowController::class, 'validateOe'])->name('validate-oe');
        Route::post('return-to-requestor', [FundRequestWorkflowController::class, 'returnToRequestor'])->name('return-to-requestor');
        Route::post('validate-staff', [FundRequestWorkflowController::class, 'validateStaff'])->name('validate-staff');
        Route::post('return-to-oe', [FundRequestWorkflowController::class, 'returnToOe'])->name('return-to-oe');
        Route::post('approve', [FundRequestWorkflowController::class, 'approve'])->name('approve');
        Route::post('not-approve', [FundRequestWorkflowController::class, 'notApprove'])->name('not-approve');
        Route::post('ap-voucher', [FundRequestWorkflowController::class, 'createApVoucher'])->name('ap-voucher');
        Route::post('cv-number', [FundRequestWorkflowController::class, 'provideCvNumber'])->name('cv-number');
        Route::post('disburse', [FundRequestWorkflowController::class, 'disburse'])->name('disburse');
        Route::post('cancel', [FundRequestWorkflowController::class, 'cancel'])->name('cancel');
    });

    // Fund request attachments (private disk; served only through download)
    Route::post('fund-requests/{fund_request}/attachments', [AttachmentController::class, 'store'])
        ->name('fund-requests.attachments.store');
    Route::get('fund-requests/{fund_request}/attachments/{attachment}', [AttachmentController::class, 'download'])
        ->name('fund-requests.attachments.download');
    Route::delete('fund-requests/{fund_request}/attachments/{attachment}', [AttachmentController::class, 'destroy'])
        ->name('fund-requests.attachments.destroy');

    // Cash Advance Liquidation — started from a disbursed Cash Advance
    Route::get('liquidations', [LiquidationController::class, 'index'])->name('liquidations.index');
    Route::post('fund-requests/{fund_request}/liquidation', [LiquidationController::class, 'store'])
        ->name('fund-requests.liquidation.store');

    Route::prefix('liquidations/{liquidation}')->name('liquidations.')->group(function () {
        Route::get('/', [LiquidationController::class, 'show'])->name('show');
        Route::get('edit', [LiquidationController::class, 'edit'])->name('edit');
        Route::put('/', [LiquidationController::class, 'update'])->name('update');

        // Expense breakdown (items) and their supporting documents
        Route::get('items/create', [LiquidationItemController::class, 'create'])->name('items.create');
        Route::post('items', [LiquidationItemController::class, 'store'])->name('items.store');
        Route::get('items/{item}/edit', [LiquidationItemController::class, 'edit'])->name('items.edit');
        Route::put('items/{item}', [LiquidationItemController::class, 'update'])->name('items.update');
        Route::delete('items/{item}', [LiquidationItemController::class, 'destroy'])->name('items.destroy');
        Route::post('items/{item}/attachments', [LiquidationItemController::class, 'storeAttachments'])->name('items.attachments.store');
        Route::get('items/{item}/attachments/{attachment}', [LiquidationItemController::class, 'downloadAttachment'])->name('items.attachments.download');
        Route::delete('items/{item}/attachments/{attachment}', [LiquidationItemController::class, 'destroyAttachment'])->name('items.attachments.destroy');

        // Tag an item as a Questionable Expense (OE "Liquidation okay?" / Staff "Verification okay?")
        Route::post('items/{item}/tag-questionable', [LiquidationWorkflowController::class, 'tag'])->name('items.tag');
        Route::delete('items/{item}/tag-questionable', [LiquidationWorkflowController::class, 'untag'])->name('items.untag');

        // Workflow
        Route::post('submit', [LiquidationWorkflowController::class, 'submit'])->name('submit');
        Route::post('validate-oe', [LiquidationWorkflowController::class, 'validateOe'])->name('validate-oe');
        Route::post('return-for-edit', [LiquidationWorkflowController::class, 'returnForEdit'])->name('return-for-edit');
        Route::post('verify', [LiquidationWorkflowController::class, 'verify'])->name('verify');
        Route::post('return-for-revision', [LiquidationWorkflowController::class, 'returnForRevision'])->name('return-for-revision');
        Route::post('prepare-apv', [LiquidationWorkflowController::class, 'prepareApv'])->name('prepare-apv');
        Route::post('approve-apv', [LiquidationWorkflowController::class, 'approveApv'])->name('approve-apv');
        Route::post('reject-apv', [LiquidationWorkflowController::class, 'rejectApv'])->name('reject-apv');
        Route::post('close', [LiquidationWorkflowController::class, 'close'])->name('close');
        Route::post('reimburse', [LiquidationWorkflowController::class, 'reimburse'])->name('reimburse');
    });

    // Questionable Expense — its own process
    Route::get('questionable-expenses', [QuestionableExpenseController::class, 'index'])->name('questionable-expenses.index');

    Route::prefix('questionable-expenses/{questionable_expense}')->name('questionable-expenses.')->group(function () {
        Route::get('/', [QuestionableExpenseController::class, 'show'])->name('show');
        Route::post('validate-hard-copy', [QuestionableExpenseController::class, 'validateHardCopy'])->name('validate-hard-copy');
        Route::post('generate-ir', [QuestionableExpenseController::class, 'generateIr'])->name('generate-ir');
        Route::post('receive-ir', [QuestionableExpenseController::class, 'receiveIr'])->name('receive-ir');
        Route::post('salary-deduction', [QuestionableExpenseController::class, 'subjectToDeduction'])->name('salary-deduction');
        Route::post('proof-of-deduction', [QuestionableExpenseController::class, 'secureProof'])->name('proof-of-deduction');
        Route::post('close', [QuestionableExpenseController::class, 'close'])->name('close');
        Route::post('attachments', [QuestionableExpenseController::class, 'storeAttachments'])->name('attachments.store');
        Route::get('attachments/{attachment}', [QuestionableExpenseController::class, 'downloadAttachment'])->name('attachments.download');
        Route::delete('attachments/{attachment}', [QuestionableExpenseController::class, 'destroyAttachment'])->name('attachments.destroy');
    });

    // Revolving Fund — funds, voyages, ledger (Admin sets funds up)
    Route::get('revolving-funds', [RevolvingFundController::class, 'index'])->name('revolving-funds.index');
    Route::get('revolving-funds/create', [RevolvingFundController::class, 'create'])->name('revolving-funds.create');
    Route::post('revolving-funds', [RevolvingFundController::class, 'store'])->name('revolving-funds.store');
    Route::get('revolving-funds/{revolving_fund}', [RevolvingFundController::class, 'show'])->name('revolving-funds.show');
    Route::get('revolving-funds/{revolving_fund}/edit', [RevolvingFundController::class, 'edit'])->name('revolving-funds.edit');
    Route::put('revolving-funds/{revolving_fund}', [RevolvingFundController::class, 'update'])->name('revolving-funds.update');
    Route::post('revolving-funds/{revolving_fund}/voyages', [RevolvingFundVoyageController::class, 'store'])->name('revolving-funds.voyages.store');
    Route::post('revolving-funds/{revolving_fund}/voyages/{voyage}/end', [RevolvingFundVoyageController::class, 'end'])->name('revolving-funds.voyages.end');

    // Revolving Fund Replenishment (Process B) — voyage expense details
    Route::get('revolving-fund-expenses', [RevolvingFundExpenseController::class, 'index'])->name('revolving-fund-expenses.index');
    Route::post('revolving-fund-voyages/{voyage}/expenses', [RevolvingFundExpenseController::class, 'store'])->name('revolving-fund-voyages.expenses.store');

    Route::prefix('revolving-fund-expenses/{revolving_fund_expense}')->name('revolving-fund-expenses.')->group(function () {
        Route::get('/', [RevolvingFundExpenseController::class, 'show'])->name('show');
        Route::get('edit', [RevolvingFundExpenseController::class, 'edit'])->name('edit');
        Route::put('/', [RevolvingFundExpenseController::class, 'update'])->name('update');

        Route::get('items/create', [RevolvingFundExpenseItemController::class, 'create'])->name('items.create');
        Route::post('items', [RevolvingFundExpenseItemController::class, 'store'])->name('items.store');
        Route::get('items/{item}/edit', [RevolvingFundExpenseItemController::class, 'edit'])->name('items.edit');
        Route::put('items/{item}', [RevolvingFundExpenseItemController::class, 'update'])->name('items.update');
        Route::delete('items/{item}', [RevolvingFundExpenseItemController::class, 'destroy'])->name('items.destroy');
        Route::post('items/{item}/attachments', [RevolvingFundExpenseItemController::class, 'storeAttachments'])->name('items.attachments.store');
        Route::get('items/{item}/attachments/{attachment}', [RevolvingFundExpenseItemController::class, 'downloadAttachment'])->name('items.attachments.download');
        Route::delete('items/{item}/attachments/{attachment}', [RevolvingFundExpenseItemController::class, 'destroyAttachment'])->name('items.attachments.destroy');

        Route::post('items/{item}/tag-questionable', [RevolvingFundExpenseWorkflowController::class, 'tag'])->name('items.tag');
        Route::delete('items/{item}/tag-questionable', [RevolvingFundExpenseWorkflowController::class, 'untag'])->name('items.untag');

        Route::post('submit', [RevolvingFundExpenseWorkflowController::class, 'submit'])->name('submit');
        Route::post('validate-oe', [RevolvingFundExpenseWorkflowController::class, 'validateOe'])->name('validate-oe');
        Route::post('return-to-requestor', [RevolvingFundExpenseWorkflowController::class, 'returnToRequestor'])->name('return-to-requestor');
        Route::post('validate-staff', [RevolvingFundExpenseWorkflowController::class, 'validateStaff'])->name('validate-staff');
        Route::post('return-to-oe', [RevolvingFundExpenseWorkflowController::class, 'returnToOe'])->name('return-to-oe');
        Route::post('approve', [RevolvingFundExpenseWorkflowController::class, 'approve'])->name('approve');
        Route::post('not-approve', [RevolvingFundExpenseWorkflowController::class, 'notApprove'])->name('not-approve');
        Route::post('ap-voucher', [RevolvingFundExpenseWorkflowController::class, 'createApv'])->name('ap-voucher');
        Route::post('hc-pending', [RevolvingFundExpenseWorkflowController::class, 'setHcPending'])->name('hc-pending');
        Route::post('cv-number', [RevolvingFundExpenseWorkflowController::class, 'provideCv'])->name('cv-number');
        Route::post('disburse', [RevolvingFundExpenseWorkflowController::class, 'disburse'])->name('disburse');
    });

    // Administration (ADMIN role enforced by policies inside the controllers)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['destroy']);
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
            ->name('users.toggle-active');

        // Master data maintenance — no delete; retire via the Active flag
        Route::resource('companies', CompanyController::class)->except(['show', 'destroy']);
        Route::resource('departments', DepartmentController::class)->except(['show', 'destroy']);
        Route::resource('vessel-types', VesselTypeController::class)->except(['show', 'destroy']);
        Route::resource('vessels', VesselController::class)->except(['show', 'destroy']);
        Route::resource('nature-of-costs', NatureOfCostController::class)->except(['show', 'destroy']);
        Route::resource('document-types', DocumentTypeController::class)->except(['show', 'destroy']);
    });
});
