<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
$router->group(['prefix' => 'customer','middleware' => 'auth'], static function () use ($router) {
	$router->post('/debug/test-jobs', 'DebugController@testDispatchJobs');
	$router->get('/', 'Test\TestController@index');
	$router->post('/updateCustomerPattern',[
	    'as' => 'updateCustomerPattern',
        'uses' => 'CustomerController@updateCustomerPattern'
    ]);
	$router->post('/updateCustomerLevel',[
	    'as' => 'updateCustomerLevel',
        'uses' => 'CustomerController@updateCustomerLevel'
    ]);
	$router->post('/importInsuranceReceipt', 'CustomerController@importInsuranceReceipt');
	$router->post('/convertHtmlToPdf', 'PDFController@convertHtmlToPdf');
	$router->post('/detail', 'PDFController@detail');
	$router->post('/addTreatmentPlaning', 'PDFController@addTreatmentPlaning');
	$router->post('/listTreatmentPlan', 'PDFController@listTreatmentPlan');
	$router->post('/listMoneyCollector', 'CustomerController@listMoneyCollector');
	$router->post('/changePhoneNumberFromZalo', 'CustomerController@changePhoneNumberFromZalo');
	$router->post('/service-delete-history', 'CustomerController@getServiceDeleteHistory');
	$router->post('/get-customer-debt-summary', 'CustomerController@getCustomerDebtSummary');
	$router->post('/get-customer-debt-detail', 'CustomerController@getCustomerDebtDetail');
	$router->post('/get-customer-info', 'CustomerController@getCustomerByIdNumber');
	$router->post('/create-order', 'CustomerController@createOrderDetail');
	$router->post('/delete-order', 'CustomerController@deleteOrderDetail');
	$router->post('/get-treatment', 'CustomerController@getTreatmentByCustomerId');
	$router->post('/change-status-order', 'CustomerController@changeStatusOrderDetail');
	$router->post('/history-change-order', 'CustomerController@historyChangeOrderDetail');
	$router->post('/spin', 'CustomerController@spinWheel');
	$router->post('/get-prize', 'CustomerController@getCustomerPrize');
	$router->post('/get-spin-wheel', 'CustomerController@getSpinWheel');
	$router->post('/info-spin-wheel', 'CustomerController@infoSpinWheel');
	$router->post('/confirm-spin-results', 'CustomerController@confirmSpinResults');
	$router->post('/add-or-update', 'CustomerController@addOrUpdateInvisalign');
	$router->post('/staff-by-branch', 'CustomerController@listStaffByBranch');
	$router->post('/staff-by-branch-in-day', 'CustomerController@listStaffByBranchInDay');
});

$router->group(['prefix' => 'customer/cdn-image','middleware' => 'auth'], static function () use ($router) {
	$router->get('/list', 'CustomerImageController@getCDNImages');
	$router->post('/add', 'CustomerImageController@addCDNImage');
	$router->post('/upload-photo', 'CustomerImageController@uploadPhoto');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/bank'], static function () use ($router) {
	$router->post('/create', 'CustomerBankController@addCustomerBank');
	$router->post('/listAccount', 'CustomerBankController@listCustomerBank');
	$router->post('/update', 'CustomerBankController@updateCustomerBank');
	$router->post('/list', 'CustomerBankController@listBank');
	$router->post('/delete', 'CustomerBankController@deleteCustomerBank');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/dental-warranty'], static function () use ($router) {
	$router->post('/list-by-customer', 'DentalWarrantyController@findByCustomerId');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/paper-work'], static function () use ($router) {
	$router->post('/list-by-customer', 'CustomerPaperWorkController@findByCustomer');
	$router->post('/save', 'CustomerPaperWorkController@saveCustomerPaperWork');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/treatment'], static function () use ($router) {
	$router->post('/list-prescription-medicine', 'TreatmentController@getPrescriptionMedicines');
	$router->post('/treatment-active', 'TreatmentController@getTreatmentActive');
	$router->post('/add-promotion-treatment-offer', 'TreatmentController@addPromotionTreatmentOffer');
	$router->post('/remove-promotion-treatment-offer', 'TreatmentController@removePromotionTreatmentOffer');
	$router->post('/list', 'TreatmentController@listTreatment');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/paper-work'], static function () use ($router) {
	$router->post('/list-by-customer', 'CustomerPaperWorkController@findByCustomer');
	$router->post('/save', 'CustomerPaperWorkController@saveCustomerPaperWork');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/relationship'], static function () use ($router) {
	$router->post('/type',[
	    'as' => 'type',
        'uses' => 'ContactRelationTypeController@getContactRelationTypeList'
	]);
	$router->post('/create',[
	    'as' => 'create',
        'uses' => 'ContactRelationshipController@store'
	]);

	$router->post('/getRelation',[
	    'as' => 'getRelation',
        'uses' => 'ContactRelationshipController@getRelation'
	]);

	$router->post('/update',[
	    'as' => 'update',
        'uses' => 'ContactRelationshipController@updateRelation'
	]);

	$router->post('/delete',[
	    'as' => 'delete',
        'uses' => 'ContactRelationshipController@destroy'
	]);
	

});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/implant-order'], static function () use ($router) {
	$router->post('/list-by-customer', 'ImplantOrderController@listByCustomer');
	$router->post('/create', 'ImplantOrderController@create');
	$router->post('/list', 'ImplantOrderController@list');
	$router->post('/detail', 'ImplantOrderController@detailImplantOrder');
	$router->post('/list-history', 'ImplantOrderController@listHistory');
	$router->post('/update', 'ImplantOrderController@update');
	$router->post('/list-implant-supplier', 'ImplantOrderController@listImplantSupplier');
	$router->post('/list-implant-technical-specification', 'ImplantOrderController@listImplantTechnicalSpecification');
	
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/labo-order'], static function () use ($router) {
	$router->post('/create-comment-order', 'LaboOrderController@createCommentOrder');
	$router->post('/list-comment-order', 'LaboOrderController@getListCommentOrder');
	$router->post('/count', 'LaboOrderController@countLaboOrderRating');
	$router->post('/get-style-order', 'LaboOrderController@getLaboOrderStyle');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/report'], static function () use ($router) {
	$router->post('/export-health-records', 'HealthRecordsController@exportHealthRecords');
	$router->post('/mkt-cash-checkin', 'ReportController@reportMKTCashCheckin');
	$router->post('/branch-daily-active', 'ReportBranchController@getDailyActive');
	$router->post('/branch-daily-history', 'ReportBranchController@getDailyHistory');
	$router->post('/branch-daily-detail', 'ReportBranchController@getDailyDetail');
	$router->post('/create-content-branch-daily', 'ReportBranchController@createContentReport');
	$router->post('/create-da-content-branch-daily', 'ReportBranchController@createDoctorAssistantContentReport');
	$router->post('/create-comment-branch-daily', 'ReportBranchController@createCommentReport');
	$router->post('/net-cash-collection', 'ReportController@getNetCashCollection');
	$router->post('/rank', 'ReportController@getRankCustomer');
	$router->post('/order-measuring-consulting', 'ReportController@getOrderMeasuringConsulting');
	$router->post('/count-order-measuring-consulting', 'ReportController@countOrderMeasuringConsulting');
	$router->post('/count-consulting-performance', 'ReportController@countConsultingPerformance');
	$router->post('/consulting-performance', 'ReportController@getConsultingPerformance');
	$router->post('/consulting-performance-by-branch', 'ReportController@getConsultingPerformanceByBranch');
	$router->post('/count-customer-care', 'ReportController@countCustomerCare');
	$router->post('/lucky-draw-spin', 'ReportController@getLuckyDrawSpinsReport');
	$router->post('/export-ticket-support', 'ReportController@exportTicketSupport');
	$router->post('/export-consulted-services-report', 'ReportController@exportConsultedServicesReport');
	$router->post('/export-consulted-success-services-report', 'ReportController@exportConsultedSuccessServicesReport');
	$router->post('/export-customer-reviews-report', 'ReportController@exportCustomerReviewsReport');
	$router->post('/export-professional-support-treatment-report', 'ReportController@exportProfessionalSupportTreatmentReport');
	$router->post('/get-receipts-by-staff-in-month', 'ReportController@getReceiptsByStaffInMonth');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/customer-care'], static function () use ($router) {
	$router->post('/list-consulting', 'CustomerCareController@getCustomerCareConsulting');
	$router->post('/list-consulting-by-customer', 'CustomerCareController@getCustomerCareConsultingByCustomer');
	$router->post('/list', 'CustomerCareController@getCustomerCareConsultingAll');
	$router->post('/list-treatment-progress', 'CustomerCareController@getTreatmentProgress');
	$router->post('/list-treatment-progress-doctor', 'CustomerCareController@getTreatmentProgressForDoctor');
	$router->post('/list-evaluation-orthodontic', 'CustomerCareController@getEvaluationOrthodontic');
	$router->post('/list-evaluation-orthodontic-doctor', 'CustomerCareController@getEvaluationOrthodonticForDoctor');
	$router->post('/process-evaluation-orthodontic', 'CustomerCareController@processEvaluationOrthodontic');
	$router->post('/detail-evaluation-orthodontic', 'CustomerCareController@detailEvaluationOrthodontic');
	$router->post('/history-evaluation-orthodontic', 'CustomerCareController@historyEvaluationOrthodontic');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/partner-company'], static function () use ($router) {
	$router->post('/all-insurance', 'PartnerCompanyController@getAllInsuranceCompany');
	$router->post('/insurance-by-branch', 'PartnerCompanyController@getInsuranceCompanyByBranch');
	$router->post('/contact-insurance', 'PartnerCompanyController@getContactInsurance');
	$router->post('/overtime-contract-insurance', 'PartnerCompanyController@getOutOfHoursInsurers');
	$router->post('/edit-insurance-by-branch', 'PartnerCompanyController@editInsuranceByBranch');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/rating'], static function () use ($router) {
	$router->post('/detail', 'RatingController@detail');
	$router->post('/update-customer', 'RatingController@updateCustomerRatingWeb');
	$router->post('/import-summary-by-month', 'RatingController@importSummaryByMonth');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/report'], static function () use ($router) {
	$router->post('/treatment', 'ReportController@doctorTreatmentReport');
	$router->post('/get-doctor-level-criteria', 'ReportController@getDoctorLevelCriteria');
	$router->post('/tracking-check-ip', 'ReportController@getTrackingCheckIP');
	$router->post('/tracking-access-customer', 'ReportController@getTrackingAccessCustomer');
	$router->post('/advisory', 'ReportController@getAdvisoryReport');
	$router->post('/count-advisory', 'ReportController@getAdvisoryReportCount');
	$router->post('/order-measuring-consulting', 'ReportController@getOrderMeasuringConsulting');
	$router->post('/count-order-measuring-consulting', 'ReportController@countOrderMeasuringConsulting');
	$router->post('/update-order-measuring-consulting', 'ReportController@updateOrderMeasuringConsulting');
	$router->post('/tracking-access-login-outside', 'ReportController@getTrackingAccessLoginOutside');
	$router->post('/tracking-verify-access-customer', 'ReportController@getTrackingVerifyAccessCustomer');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/appointment'], static function () use ($router) {
	$router->post('/change-doctor', 'AppointmentController@changeDoctorOfAppointment');
	$router->post('/add-doctor', 'AppointmentController@addDoctorOfAppointment');
	$router->post('/remove-doctor', 'AppointmentController@removeDoctorOfAppointment');
	$router->post('/save-doctor-assistant', 'AppointmentController@saveDoctorAssistantOfAppointment');
	$router->post('/get-appointment-and-rating', 'AppointmentController@getAppointmentAndRating');
    $router->post('/list-doctor-and-assistant', 'AppointmentController@listDoctorAndAssistantByCustomer');
	$router->post('/status-history', 'AppointmentController@getAppointmentStatusHistory');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/document'], static function () use ($router) {
	$router->post('/get-by-customer', 'CustomerDocumentController@getDocumentByCustomer');
	$router->post('/create-document', 'CustomerDocumentController@createDocument');
	$router->post('/edit-document', 'CustomerDocumentController@editDocument');
	$router->post('/remove-document', 'CustomerDocumentController@removeDocument');
	$router->post('/upload-document', 'CustomerDocumentController@uploadDocument');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/urgent'], static function () use ($router) {
	$router->post('/detail', 'CustomerController@getUrgentContactDetail');
	$router->post('/update', 'CustomerController@updateUrgentContact');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/infection'], static function () use ($router) {
	$router->post('/list', 'InfectionController@list');
	$router->post('/detail', 'InfectionController@detail');
	$router->post('/create', 'InfectionController@create');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/receipt'], static function () use ($router) {
	$router->post('/get-receipt-adjust', 'CustomerController@getReceiptAdjustTracking');
	$router->post('/create-receipt-adjust', 'CustomerController@createReceiptAdjustTracking');
	$router->post('/get-receipt-info', 'CustomerController@getReceiptByReceiptCode');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/ocr'], static function () use ($router) {
	$router->post('/check', 'OCRController@check');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/order'], static function () use ($router) {
	$router->post('/update-order-measuring-consulting', 'ReportController@updateOrderMeasuringConsulting');
	$router->post('/order-measuring-consulting-service-detail', 'ReportController@getOrderMeasuringConsultingServiceDetail');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/dial'], static function () use ($router) {
	$router->post('/get-country-dial-info', 'CountryDialInfoController@getAll');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/common'], static function () use ($router) {
	$router->post('/options', 'CommonController@getOptionList');
	$router->post('/list-person-title', 'CommonController@getPersonTitleList');
	$router->post('/list-gender', 'CommonController@getGenderList');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/appointment-status'], static function () use ($router) {
	$router->post('/list-history', 'AppointmentController@getAppointmentStatusHistory');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/material'], static function () use ($router) {
	$router->post('/list', 'MaterialTreatmentController@index');
	$router->post('/customer-list', 'MaterialTreatmentController@getListByCustomer');
	$router->post('/detail', 'MaterialTreatmentController@detail');
	$router->post('/update', 'MaterialTreatmentController@update');
	$router->post('/confirm', 'MaterialTreatmentController@confirm');
	$router->post('/confirm-not-using', 'MaterialTreatmentController@confirmNotUsingMaterial');
	$router->post('/detail-by-treatment-history', 'MaterialTreatmentController@detailByTreatmentHistory');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/product-manager'], static function () use ($router) {
	$router->post('/list', 'ProductManagerController@getListProduct');
	$router->post('/create', 'ProductManagerController@create');
	$router->post('/import', 'ProductManagerController@import');
	$router->get('/getListImport', 'ProductManagerController@getListImport');
	$router->post('/detail', 'ProductManagerController@getDetailProduct');
	$router->post('/remove', 'ProductManagerController@remove');
	$router->get('/getOptionLists', 'ProductManagerController@getOptionLists');
	$router->post('/check-sku', 'ProductManagerController@checkSku');
	$router->post('/list-product', 'ProductManagerController@getListProductTreatment');
	$router->post('/categories', 'CategoryController@getCategoriesParentChild');
	$router->post('/check-category', 'CategoryController@checkCategory');
	$router->post('/check-product-name', 'ProductManagerController@checkProductName');
	$router->post('/update', 'ProductManagerController@updateProduct');
	$router->post('/product-log', 'ProductManagerController@getProductLog');
	$router->group(['prefix' => 'product-history'], static function () use ($router) {
		$router->post('/list', 'ProductManagerController@getListImportExportHistories');
		$router->post('/import-detail', 'ProductManagerController@getImportHistoryDetail');
		$router->post('/export-detail', 'ProductManagerController@getExportHistoryDetail');
	});
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/sale'], static function () use ($router) {
	$router->get('/inboundOrder/getOptionLists', 'InBoundOrderController@getOptionLists');
	$router->post('/inboundOrder/create', 'InBoundOrderController@create');
	$router->post('/inboundOrder/list', 'InBoundOrderController@listProductsIR');
	$router->post('/inboundOrder/detail', 'InBoundOrderController@getDetailIR');
	$router->post('/inboundOrder/listWarehousedProducts', 'InBoundOrderController@listWarehousedProducts');
	$router->post('/inboundOrder/update', 'InBoundOrderController@updateStatusInboundOrder'); // Cập nhật trạng thái và các thông tin đi kèm khi thay đổi trạng thái phiếu nhập
	$router->post('/inboundOrder/update-all', 'InBoundOrderController@updateIR'); // Cập nhật mọi thông tin phiếu nhập
	$router->post('/inboundOrder/process-excel', 'InBoundOrderController@processExcelInbound');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/service'], static function () use ($router) {
	$router->post('/list-warranty', 'CustomerController@listWarrantyServiceByCustomer');
});

$router->group(['middleware' => ['auth'], 'prefix' => 'customer/dashboard'], static function () use ($router) {
	$router->post('/total-receipt', 'DashboardController@totalReceipt');
	$router->post('/total-appointment', 'DashboardController@totalAppointment');
	$router->post('/list-rating', 'DashboardController@listRating');
	$router->post('/total-rating', 'DashboardController@totalRating');
	$router->post('/list-consultation-service', 'DashboardController@listConsultationService');
	$router->post('/list-treatment-by-doctor', 'DashboardController@listTreatmentByDoctor');
	$router->post('/total-customer', 'DashboardController@totalCustomer');
	$router->post('/list-appointment-source', 'DashboardController@listAppointmentSource');
	$router->post('/total-receipt-by-staff', 'DashboardController@totalReceiptByStaff');
	$router->post('/total-receipt-by-doctor', 'DashboardController@totalReceiptByDoctor');

	// Dashboard V2
	$router->post('/v2/total-receipt', 'DashboardController@totalReceiptV2');
	$router->post('/v2/total-appointment', 'DashboardController@totalAppointmentV2');
	$router->post('/v2/list-consultation-service', 'DashboardController@listConsultationServiceV2');
	$router->post('/v2/list-branch', 'DashboardController@listBranch');
	$router->post('/v2/total-rating', 'DashboardController@totalRatingV2');
	$router->post('/v2/total-receipt-by-staff', 'DashboardController@totalReceiptByStaffV2');
	$router->post('/v2/total-receipt-by-doctor', 'DashboardController@totalReceiptByDoctorV2');
	$router->post('/v2/get-receipts-by-staff', 'DashboardController@getReceiptsByStaff');

	// Dashboard Insert Data
	$router->post('/insert-branch-daily', 'DashboardController@insertBranchDaily');
	$router->post('/insert-branch-service-daily', 'DashboardController@insertBranchServiceDaily');
	$router->post('/insert-customer-rating-daily', 'DashboardController@insertCustomerRatingDaily');
	$router->post('/insert-customer-source-summary', 'DashboardController@insertCustomerSourceSummary');
	$router->post('/insert-staff-effective-daily', 'DashboardController@insertStaffEffectiveDaily');
	$router->post('/insert-doctor-effective-daily', 'DashboardController@insertDoctorEffectiveDaily');
	
	// Refresh all dashboard data
	$router->post('/refresh-dashboard-data', 'DashboardController@refreshDashboardData');

	// Dashboard Insert Data Promotion
	$router->post('/insert-promotion-dashboard', 'DashboardController@insertPromotionDashboard');

	// Dashboard Promotion Read APIs
	$router->post('/promotion-summary', 'DashboardController@getPromotionSummary');
	$router->post('/promotion-by-type', 'DashboardController@getPromotionByType');
	$router->post('/promotion-by-service', 'DashboardController@getPromotionByService');
	$router->post('/promotion-by-branch', 'DashboardController@getPromotionByBranch');
	$router->post('/promotion-top-customers', 'DashboardController@getTopCustomersByPromotion');
	
	// Dashboard Checkin & Chair Usage APIs
	$router->post('/checkin-by-weekday', 'DashboardController@getCheckinByWeekday');
	$router->post('/chair-usage-by-weekday', 'DashboardController@getChairUsageByWeekday');
	$router->post('/checkin-by-hour', 'DashboardController@getCheckinByHour');
	$router->post('/hourly-usage', 'DashboardController@getHourlyUsage');
	$router->post('/checkin-weekday-by-hour', 'DashboardController@getCheckinWeekdayByHour');
	$router->post('/average-stay-time', 'DashboardController@getAverageStayTime');

	// Dashboard Customer Orthodontic
	$router->post('/total-customer-orthodontic', 'DashboardController@totalCustomerOrthodontic');
	$router->post('/total-customer-step', 'DashboardController@totalCustomerOrthodonticByStep');
	$router->post('/total-customer-detail', 'DashboardController@totalCustomerOrthodonticByStepDetail');
	// Dashboard Appointment
	$router->post('/count-appointment', 'DashboardController@countAppointment');

	// Dashboard Customer Orthodontic V2
	$router->post('/v2/orthodontic/clinic-manager/summary', 'OrthodonticController@clinicManagerSummary');
	$router->post('/v2/orthodontic/doctor/summary', 'OrthodonticController@doctorSummary');
	$router->post('/v2/orthodontic/executive/summary', 'OrthodonticController@executiveSummary');
	$router->post('/v2/orthodontic/priority-list', 'OrthodonticController@priorityList');
	$router->post('/v2/orthodontic/near-completion', 'OrthodonticController@nearCompletion');
	$router->post('/v2/orthodontic/doctor-list', 'OrthodonticController@doctorList');
	$router->post('/v2/orthodontic/build-snapshot', 'OrthodonticController@buildKpiSnapshot');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/receipt'], static function () use ($router) {
	$router->post('/list-service', 'ReceiptController@listService');
	$router->post('/create', 'ReceiptController@createReceipt');
	$router->post('/update', 'ReceiptController@updateReceipt');
	$router->post('/create-pay-installments', 'ReceiptController@createPayInstallments');
	$router->post('/cancel-receipt-pending', 'ReceiptController@cancelReceiptPending');
	$router->post('/check-receipt-pending', 'ReceiptController@checkReceiptPending');
	$router->post('/list-customer-installment', 'ReceiptController@listCustomerInstallment');
	$router->post('/total-customer-installment', 'ReceiptController@totalCustomerInstallment');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/expenditure'], static function () use ($router) {
	$router->post('/create', 'ExpenditureController@createExpenditure');
	$router->post('/list-service', 'ExpenditureController@listService');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/loyalty'], static function () use ($router) {
	$router->post('/point', 'LoyaltyPointController@getPointByCustomer');
	$router->post('/detail', 'LoyaltyPointController@getPointDetailByCustomer');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/helpdesk'], static function () use ($router) {
	$router->post('/update-invoice-discount', 'DepositTransactionController@updateInvoiceDiscount');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/webhook'], static function () use ($router) {
	$router->post('/finish-receipt-pending', 'ReceiptController@webhookFinishReceiptPending');
	$router->post('/cancel-receipt-pending', 'ReceiptController@webhookCancelReceiptPending');
});
$router->group(['middleware' => 'auth', 'prefix' => 'customer/ai'], static function () use ($router) {
	$router->post('/detail-consultation-training', 'AIController@detailConsultationTraining');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/gateway'], static function () use ($router) {
	$router->post('/save-customer-his', 'CustomerGatewayController@saveCustomerHIS');
	$router->post('/check-url', 'CustomerGatewayController@checkUrlHIS');
});

// Product
$router->group(['middleware' => 'auth', 'prefix' => 'customer/inventory/product'], static function () use ($router) {
	$router->post('/list-product', 'ProductController@index');
	$router->post('/detail-product', 'ProductController@show');
	$router->post('/create-product', 'ProductController@store');
	$router->post('/update-product', 'ProductController@update');
	$router->post('/toggle-product', 'ProductController@toggleState');
	$router->post('/list-product-by-supplier', 'ProductController@listProductBySupplier');
});

// Product Category
$router->group(['middleware' => 'auth', 'prefix' => 'customer/inventory/product-category'], static function () use ($router) {
	$router->post('/list-category', 'ProductCategoryController@index');
});

// Unit
$router->group(['middleware' => 'auth', 'prefix' => 'customer/inventory/unit'], static function () use ($router) {
	$router->post('/list-unit', 'UnitController@index');
});

// Supplier
$router->group(['middleware' => 'auth', 'prefix' => 'customer/inventory/supplier'], static function () use ($router) {
	$router->post('/list-supplier', 'SupplierController@index');
	$router->post('/list-supplier-by-product', 'SupplierController@listSupplierByProduct');
	$router->post('/detail-supplier', 'SupplierController@show');
	$router->post('/create-supplier', 'SupplierController@store');
	$router->post('/update-supplier', 'SupplierController@update');
	$router->post('/toggle-supplier', 'SupplierController@toggleState');
});

// Order Request (phía phòng khám)
$router->group(['middleware' => 'auth', 'prefix' => 'customer/inventory/order-request'], static function () use ($router) {
	$router->post('/list', 'OrderRequestController@index');
	$router->post('/list-all', 'OrderRequestController@listOrderRequest');
	$router->post('/detail', 'OrderRequestController@show');
	$router->post('/create', 'OrderRequestController@store');
	$router->post('/update', 'OrderRequestController@update');
	$router->post('/update-status', 'OrderRequestController@updateStatus');
	$router->post('/update-expected-delivery-date', 'OrderRequestController@updateExpectedDeliveryDate');
	
	// Department Demand APIs
	$router->post('/department-demand-list', 'OrderRequestController@getDepartmentDemandList');
	$router->post('/department-demand-detail', 'OrderRequestController@getDepartmentDemandDetail');
	$router->post('/create-delivery-from-demand', 'OrderRequestController@createDeliveryFromDemand');
	
	// Delivery APIs
	$router->post('/delivery-list', 'OrderRequestController@getDeliveryList');
	$router->post('/delivery-detail', 'OrderRequestController@getDeliveryDetail');
	$router->post('/confirm-delivery', 'OrderRequestController@confirmDelivery');
});

// Purchase Order
$router->group(['middleware' => 'auth', 'prefix' => 'customer/inventory/purchase-order'], static function () use ($router) {
	$router->post('/list', 'PurchaseOrderController@index');
	$router->post('/detail', 'PurchaseOrderController@show');
	$router->post('/create', 'PurchaseOrderController@store');
	$router->post('/update', 'PurchaseOrderController@update');
	$router->post('/send', 'PurchaseOrderController@send');
	$router->post('/export', 'PurchaseOrderController@export');
});

// Inbout Request (phiếu nhập kho)
$router->group(['middleware' => 'auth', 'prefix' => 'customer/inventory/inbout-request'], static function () use ($router) {
	$router->post('/create', 'InboutRequestController@store');
});

// Inventory (tồn kho)
$router->group(['middleware' => 'auth', 'prefix' => 'customer/inventory/inventory'], static function () use ($router) {
	$router->post('/list-stock', 'InventoryController@index');
	$router->post('/summary-stock', 'InventoryController@summary');
	$router->post('/history-stock', 'InventoryController@history');
});

$router->group(['middleware' => 'auth', 'prefix' => 'customer/insurance'], static function () use ($router) {
	$router->post('/get-customer', 'CustomerController@infoCustomer');
	$router->post('/info', 'CustomerController@infoInsurance');
});

$router->group(['prefix' => 'customer/profile-staff','middleware' => 'auth'], static function () use ($router) {
	$router->post('/summary', 'ProfileStaffController@getStaffProfile');
	$router->post('/services', 'ProfileStaffController@getServiceAnalysis');
	$router->post('/rating', 'ProfileStaffController@getRating');
	$router->post('/revenue-chart', 'ProfileStaffController@getRevenueChart');
});

//log api
if(env('APP_DEBUG')){
	$router->group(['namespace' => '\Rap2hpoutre\LaravelLogViewer'], function() use ($router) {
		$router->get('/debug/logs', 'LogViewerController@index');
	});
}

// ─────────────────────────────────────────────────────────────────────────────
// Insurance Hub — prefix: /customer/insurance-hub
// ─────────────────────────────────────────────────────────────────────────────

$router->group([
    'prefix'     => 'customer/insurance-hub',
    'middleware' => 'auth',
    'namespace'  => 'InsuranceHub',
], function () use ($router) {

    // Providers & workflow
    $router->get('/{code}/request-workflow', 'InsuranceHubController@getRequestWorkflow');
    $router->get('/{code}/claim-workflow',   'InsuranceHubController@getClaimWorkflow');

    // ── Requests ────────────────────────────────────────────────────────────
    $router->post('/requests/list',                   'InsuranceRequestController@index');
    $router->post('/requests',                        'InsuranceRequestController@store');
    $router->post('/requests/delete',                 'InsuranceRequestController@deleteDraft');
    $router->post('/requests/{id}',                    'InsuranceRequestController@show');
    $router->post('/requests/{id}/submit-claim',       'InsuranceRequestController@submitClaim');
    $router->post('/requests/{id}/edit',               'InsuranceRequestController@edit');
    $router->post('/requests/{id}/confirmation-pdf',   'InsuranceRequestController@confirmationPdf');

//    $router->put('/requests/{id}',                    'InsuranceRequestController@update');
//    $router->post('/requests/{id}/submit',            'InsuranceRequestController@submit');
//    $router->delete('/requests/{id}/cancel',          'InsuranceRequestController@cancel');
//    $router->post('/requests/{id}/complete',          'InsuranceRequestController@complete');
//    $router->get('/requests/{id}/status-history',     'InsuranceRequestController@statusHistory');
//    $router->post('/requests/{id}/supplements',       'InsuranceRequestController@supplement');
//    $router->get('/requests/{id}/messages',           'InsuranceRequestController@listMessages');
//    $router->post('/requests/{id}/messages',          'InsuranceRequestController@sendMessage');

//    // ── Claims ──────────────────────────────────────────────────────────────
//    $router->get('/claims',                      'InsuranceClaimController@index');
//    $router->get('/claims/{id}',                 'InsuranceClaimController@show');
//    $router->post('/claims/{id}/attachments',    'InsuranceClaimController@addAttachment');
//    $router->post('/claims/{id}/submit',         'InsuranceClaimController@submit');
//
//    // ── Notifications ────────────────────────────────────────────────────────
//    $router->get('/notifications',          'InsuranceNotificationController@index');
//    $router->put('/notifications/{id}',     'InsuranceNotificationController@markRead');
});

