<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\CouponController;

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Admin\AppointmentsController as AdminAppointmentController;
use App\Http\Controllers\Admin\RatingController as AdminRatingController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;


// Health & Server Test
Route::get('/test', function () {
    return response('Server is working!');
});

// Serve uploaded files
Route::get('/uploads/{filename}', function ($filename) {
    $path = public_path('uploads/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});

// Authentication & Profile
Route::post('/login', [AuthController::class, 'login']);
Route::get('/client/profile/{clientid}', [AuthController::class, 'getClientProfile']);
Route::post('/client/mark-first-login', [AuthController::class, 'markFirstLoginCompleted']);
Route::delete('/delete-student/{clientid}/{student_id}', [AuthController::class, 'deleteStudent']);
Route::post('/forget_password', [AuthController::class, 'forgetpassword']);
Route::post('/changepassword/{clientid}', [AuthController::class, 'changepassword']);
Route::post('/oldnewpassword', [AuthController::class, 'oldnewpassword']);
Route::post('/clients', [AuthController::class, 'clientAPIPOST']);
Route::post('/students', [AuthController::class, 'studentCreate']);
Route::post('/enquiry', [AuthController::class, 'enquiryAPIPOST']);
Route::post('/clientemailsend', [AuthController::class, 'clientemailsend']);
Route::post('/clientemailsend/', [AuthController::class, 'clientemailsend']);
Route::get('/clientsdata/{clientid}', [AuthController::class, 'getClientById']);
Route::get('/clientDataget', [AuthController::class, 'ClientsetDatabae']);
Route::get('/client/status-check', [AuthController::class, 'autoStatusCheck']);
Route::get('/clientpricestatus/{clientid}', [AuthController::class, 'priceStatuseclientid']);
Route::get('/clientbookdata/{id}', [AuthController::class, 'getClientBookData']);

// Contractors (Tutors) & Availability
Route::get('/contractors', [AuthController::class, 'saveAllContractors']);
Route::get('/contractorsalldata', [AuthController::class, 'GetallContractors']);
Route::get('/contractorsbyid/{id}', [AuthController::class, 'GetByIdContractors']);
Route::get('/contractorsavailability/{id}', [AuthController::class, 'contractor_availabilityAPIGET']);
Route::get('/availability', [AuthController::class, 'AllContractorsavailability']);
Route::get('/availabilityalldata', [AuthController::class, 'Getallavailability']);
Route::get('/filter_tutor', [AuthController::class, 'FilterTutor']);

// Subjects & Locations & Countries
Route::get('/subjects', [AuthController::class, 'subjectsAPIGET']);
Route::get('/subjectsalldata', [AuthController::class, 'GetAlldatasubject']);
Route::match(['get', 'post'], '/subjectFilter', [AuthController::class, 'ClientSubjectnameFilterData']);
Route::match(['get', 'post'], '/subjectFilter/', [AuthController::class, 'ClientSubjectnameFilterData']);
Route::get('/location', [AuthController::class, 'locationAPIGET']);
Route::get('/locationalldata', [AuthController::class, 'GetAlldatalocation']);
Route::get('/countries', [AuthController::class, 'saveAllCountry']);
Route::get('/countriesAll', [AuthController::class, 'GetallCountry']);

// Appointments & Services
Route::post('/appointments', [AuthController::class, 'appointmentsAPIPOST']);
Route::post('/appointments/', [AuthController::class, 'appointmentsAPIPOST']);
Route::put('/appointments/{id}', [AuthController::class, 'appointmentputApi']);
Route::get('/appointments/{id}', [AuthController::class, 'appointmentfilterApi']);
Route::get('/appointmentstwo/{id}', [AuthController::class, 'appointmentfilterApitwo']);
Route::get('/appointmentsthree/{id}', [AuthController::class, 'appointmentfilterApithree']);
Route::get('/appointments', [AuthController::class, 'appointmentsAPIGET']);
Route::get('/appointment', [AppointmentController::class, 'saveAllAppointments']);
Route::get('/allappointment', [AppointmentController::class, 'GetallAppointments']);
Route::post('/services', [AuthController::class, 'servicesAPIPOST']);
Route::post('/services/', [AuthController::class, 'servicesAPIPOST']);
Route::get('/services/{id}', [AuthController::class, 'servicesAPIgetbyid']);
Route::get('/service/{id}', [AuthController::class, 'getbyidShowService']);
Route::get('/save-all-client-student-appointments', [AuthController::class, 'saveAllClientsStudentAppointments']);

// Packages & Holidays
Route::post('/packega', [PackageController::class, 'PackegaCreate']);
Route::get('/packega/{id}', [PackageController::class, 'PackegaById']);
Route::get('/allpackega', [PackageController::class, 'PackegaAllshow']);
Route::get('/allpackegastudent', [PackageController::class, 'PackegaAllshowstudent']);
Route::get('/holidays', [PackageController::class, 'HolidayAllShow']);
Route::get('/holidays/year/{year}', [PackageController::class, 'HolidayByYear']);
Route::get('/holidays/upcoming', [PackageController::class, 'UpcomingHolidays']);
Route::post('/terms/accept', [PackageController::class, 'AcceptTerms']);

// Client Packages (Paid & Free Assessments)
Route::post('/client-packages', [AuthController::class, 'createClientPackageSingleTable']);
Route::post('/client-package/reschedule', [AuthController::class, 'updatePackageRescheduleCount']);
Route::get('/client-packages/{clientid}', [AuthController::class, 'getClientPackageByClientId']);
Route::get('/client-packagesid/{id}', [AuthController::class, 'getClientPackageById']);
Route::put('/client-packagesupdate/{id}', [AuthController::class, 'updateClientPackageById']);
Route::post('/client-packagesfree', [AuthController::class, 'createClientPackageSingleTabletwo']);
Route::post('/client-packagesfree/', [AuthController::class, 'createClientPackageSingleTabletwo']);
Route::get('/client-packagesfree/{clientid}', [AuthController::class, 'getClientPackageByClientIdtwo']);
Route::delete('/client-packagesfree/{id}', [AuthController::class, 'deleteClientPackageById']);
Route::delete('/client-packagesclientid/{clientid}', [AuthController::class, 'deleteClientPackageByClientId']);
Route::get('/freeassismentstatus/{client_id}', [AuthController::class, 'FreeAssismentstatuscheck']);

// Proforma Invoices
Route::post('/proformainvoice', [AuthController::class, 'proformaInvoiceAPIPOST']);
Route::get('/proformainvoice', [AuthController::class, 'proformaInvoiceAPIGet']);
Route::post('/proformainvoicetakepayment/{id}', [AuthController::class, 'proformaInvoicetakepaymentAPIPOST']);

// Reviews & Ratings
Route::post('/reviews', [AuthController::class, 'ReviewApi']);
Route::post('/reviewsdoctor', [RatingController::class, 'createReview']);
Route::get('/reviewsdoctor', [RatingController::class, 'getAllReviews']);
Route::get('/reviewsdoctorcount', [RatingController::class, 'getAverageRatings']);
Route::get('/reviewsdoctor/doctor/{doctor_id}', [RatingController::class, 'getDoctorReviews']);
Route::delete('/reviewsdoctor/{id}', [RatingController::class, 'deleteReview']);
Route::post('/sendReviewEmail', [AuthController::class, 'Adminandclientemailsend']);
Route::post('/sendemailadmin', [AuthController::class, 'Adminemail']);

// Coupons
Route::post('/apply-coupon', [CouponController::class, 'applyCoupon']);

// Payments (N-Genius & Stripe)
Route::post('/create-order', [PaymentController::class, 'createOrder']);
Route::get('/success', [PaymentController::class, 'success']);
Route::get('/failed', [PaymentController::class, 'failed']);
Route::get('/cancel', [PaymentController::class, 'cancel']);
Route::post('/order_status', [PaymentController::class, 'orderStatus']);
Route::get('/payment-status/{ref}', [PaymentController::class, 'paymentStatus']);
Route::get('/payments/by-client/{clientId}', [PaymentController::class, 'paymentsByClient']);
Route::post('/create-payment-intent', [AuthController::class, 'paymentGetwaystripe']);

/*
|--------------------------------------------------------------------------
| Admin API Routes (/api/admin/*)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    // Auth & Profile
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::get('/client/profile/{clientid?}', [AdminAuthController::class, 'getClientProfile']);
    Route::post('/client/mark-first-login', [AdminAuthController::class, 'markFirstLoginCompleted']);
    Route::delete('/delete-student/{clientid}/{student_id}', [AdminAuthController::class, 'deleteStudent']);
    Route::post('/forget_password', [AdminAuthController::class, 'forgetpassword']);
    Route::post('/changepassword/{clientid}', [AdminAuthController::class, 'changepassword']);
    Route::post('/oldnewpassword', [AdminAuthController::class, 'oldnewpassword']);

    // Admin Client List
    Route::get('/all-clients', [AdminAuthController::class, 'getAllClients']);
    Route::get('/clientsdata/{clientid}', [AdminAuthController::class, 'getClientById']);
    Route::get('/clientDataget', [AdminAuthController::class, 'ClientsetDatabae']);
    Route::post('/clientemailsend', [AdminAuthController::class, 'clientemailsend']);
    Route::post('/clientemailsend/', [AdminAuthController::class, 'clientemailsend']);
    Route::post('/clients', [AdminAuthController::class, 'clientAPIPOST']);
    Route::post('/students', [AdminAuthController::class, 'studentCreate']);
    Route::post('/enquiry', [AdminAuthController::class, 'enquiryAPIPOST']);

    // Branch 1017 & Branch 28866 Clients
    Route::get('/all-clientsBranch1017', [AdminAuthController::class, 'getBranch1017Clients']);
    Route::get('/all-clientsBranch28866', [AdminAuthController::class, 'getBranch28866Clients']);
    Route::get('/all-clients-dropdown1017', [AdminAuthController::class, 'getAllClientsDropdown1017']);
    Route::get('/all-clients-dropdown28866', [AdminAuthController::class, 'getAllClientsDropdown28866']);

    // Branch 1017 & Branch 28866 Tutors
    Route::get('/all-tutorsBranch1017', [AdminAuthController::class, 'getBranch1017Tutors']);
    Route::get('/all-tutorsBranch28866', [AdminAuthController::class, 'getBranch28866Tutors']);
    Route::get('/all-tutors-dropdown1017', [AdminAuthController::class, 'getAllTutorsDropdown1017']);
    Route::get('/all-tutors-dropdown28866', [AdminAuthController::class, 'getAllTutorsDropdown28866']);

    // Branch 1017 & Branch 28866 Students
    Route::get('/all-studentsBranch1017', [AdminAuthController::class, 'getBranch1017Students']);
    Route::get('/all-studentsBranch28866', [AdminAuthController::class, 'getBranch28866Students']);
    Route::get('/all-students-dropdown1017', [AdminAuthController::class, 'getAllStudentsDropdown1017']);
    Route::get('/all-students-dropdown28866', [AdminAuthController::class, 'getAllStudentsDropdown28866']);
    Route::get('/client-students1017/{clientId}', [AdminAuthController::class, 'getClientStudents1017']);
    Route::get('/client-students28866/{clientId}', [AdminAuthController::class, 'getClientStudents28866']);

    // Branch 1017 & Branch 28866 Invoices
    Route::get('/all-invoicesBranch1017', [AdminAuthController::class, 'getBranch1017Invoices']);
    Route::get('/all-invoicesBranch28866', [AdminAuthController::class, 'getBranch28866Invoices']);

    // Branch 1017 & Branch 28866 Appointments
    Route::get('/all-appointmentsBranch1017', [AdminAuthController::class, 'getBranch1017Appointments']);
    Route::get('/all-appointmentsBranch28866', [AdminAuthController::class, 'getBranch28866Appointments']);
    Route::get('/filter-appointments1017', [AdminAuthController::class, 'filterAppointments1017']);
    Route::get('/filter-appointments28866', [AdminAuthController::class, 'filterAppointments28866']);

    // Dashboards for Branch 1017 & Branch 28866
    Route::get('/dashboardStudentsBranch1017', [AdminAuthController::class, 'getBranch1017StudentsDashboard']);
    Route::get('/dashboardStudentsBranch28866', [AdminAuthController::class, 'getBranch28866StudentsDashboard']);
    Route::get('/dashboardTutorsBranch1017', [AdminAuthController::class, 'getBranch1017TutorsDashboard']);
    Route::get('/dashboardTutorsBranch28866', [AdminAuthController::class, 'getBranch28866TutorsDashboard']);
    Route::get('/client-dashboard1017/{clientId}', [AdminAuthController::class, 'getBranch1017ClientDashboard']);
    Route::get('/client-dashboard28866/{clientId}', [AdminAuthController::class, 'getBranch28866ClientDashboard']);
    Route::get('/dashboardInvoicesBranch1017', [AdminAuthController::class, 'getBranch1017InvoicesDashboard']);
    Route::get('/dashboardInvoicesBranch28866', [AdminAuthController::class, 'getBranch28866InvoicesDashboard']);
    Route::get('/dashboardPaymentsBranch1017', [AdminAuthController::class, 'getBranch1017PaymentsDashboard']);
    Route::get('/dashboardPaymentsBranch28866', [AdminAuthController::class, 'getBranch28866PaymentsDashboard']);
    Route::get('/dashboardAppointmentsBranch1017', [AdminAuthController::class, 'getBranch1017AppointmentsDashboard']);
    Route::get('/dashboardAppointmentsBranch28866', [AdminAuthController::class, 'getBranch28866AppointmentsDashboard']);
    Route::get('/dashboardClientsBranch1017', [AdminAuthController::class, 'getBranch1017ClientsDashboard']);
    Route::get('/dashboardClientsBranch28866', [AdminAuthController::class, 'getBranch28866ClientsDashboard']);

    // Contractors & Availability
    Route::get('/contractors', [AdminAuthController::class, 'saveAllContractors']);
    Route::get('/contractorsalldata', [AdminAuthController::class, 'GetallContractors']);
    Route::get('/contractorsbyid/{id}', [AdminAuthController::class, 'GetByIdContractors']);
    Route::get('/contractorsavailability/{id}', [AdminAuthController::class, 'contractor_availabilityAPIGET']);
    Route::get('/availability', [AdminAuthController::class, 'AllContractorsavailability']);
    Route::get('/availabilityalldata', [AdminAuthController::class, 'Getallavailability']);
    Route::get('/filter_tutor', [AdminAuthController::class, 'FilterTutor']);

    // Admin Packages & Terms
    Route::post('/packega', [AdminPackageController::class, 'PackegaCreate']);
    Route::get('/packega/{id}', [AdminPackageController::class, 'PackegaById']);
    Route::get('/allpackega', [AdminPackageController::class, 'PackegaAllshow']);
    Route::get('/allpackegastudent', [AdminPackageController::class, 'PackegaAllshowstudent']);
    Route::get('/holidays', [AdminPackageController::class, 'HolidayAllShow']);
    Route::get('/holidays/year/{year}', [AdminPackageController::class, 'HolidayByYear']);
    Route::get('/holidays/upcoming', [AdminPackageController::class, 'UpcomingHolidays']);
    Route::post('/terms/accept', [AdminPackageController::class, 'AcceptTerms']);

    // Admin Client Packages
    Route::post('/client-packages', [AdminAuthController::class, 'createClientPackageSingleTable']);
    Route::post('/client-package/reschedule', [AdminAuthController::class, 'updatePackageRescheduleCount']);
    Route::get('/client-packages/{clientid}', [AdminAuthController::class, 'getClientPackageByClientId']);
    Route::get('/client-packagesid/{id}', [AdminAuthController::class, 'getClientPackageById']);
    Route::put('/client-packagesupdate/{id}', [AdminAuthController::class, 'updateClientPackageById']);
    Route::post('/client-packagesfree', [AdminAuthController::class, 'createClientPackageSingleTabletwo']);
    Route::post('/client-packagesfree/', [AdminAuthController::class, 'createClientPackageSingleTabletwo']);
    Route::get('/client-packagesfree/{clientid}', [AdminAuthController::class, 'getClientPackageByClientIdtwo']);
    Route::delete('/client-packagesfree/{id}', [AdminAuthController::class, 'deleteClientPackageById']);
    Route::delete('/client-packagesclientid/{clientid}', [AdminAuthController::class, 'deleteClientPackageByClientId']);
    Route::get('/freeassismentstatus/{client_id}', [AdminAuthController::class, 'FreeAssismentstatuscheck']);
    Route::get('/clientbookdata/{id}', [AdminAuthController::class, 'getClientBookData']);

    // Subjects & Filters
    Route::get('/subjects', [AdminAuthController::class, 'subjectsAPIGET']);
    Route::get('/subjectsalldata', [AdminAuthController::class, 'GetAlldatasubject']);
    Route::match(['get', 'post'], '/subjectFilter', [AdminAuthController::class, 'ClientSubjectnameFilterData']);
    Route::match(['get', 'post'], '/subjectFilter/', [AdminAuthController::class, 'ClientSubjectnameFilterData']);
    Route::get('/location', [AdminAuthController::class, 'locationAPIGET']);
    Route::get('/locationalldata', [AdminAuthController::class, 'GetAlldatalocation']);
    Route::get('/countries', [AdminAuthController::class, 'saveAllCountry']);
    Route::get('/countriesAll', [AdminAuthController::class, 'GetallCountry']);

    // Admin Appointments & Services
    Route::post('/appointments', [AdminAuthController::class, 'appointmentsAPIPOST']);
    Route::post('/appointments/', [AdminAuthController::class, 'appointmentsAPIPOST']);
    Route::put('/appointments/{id}', [AdminAuthController::class, 'appointmentputApi']);
    Route::get('/appointments/{id}', [AdminAuthController::class, 'appointmentfilterApi']);
    Route::get('/appointmentstwo/{id}', [AdminAuthController::class, 'appointmentfilterApitwo']);
    Route::get('/appointmentsthree/{id}', [AdminAuthController::class, 'appointmentfilterApithree']);
    Route::get('/appointments', [AdminAuthController::class, 'appointmentsAPIGET']);
    Route::get('/appointment', [AdminAppointmentController::class, 'saveAllAppointments']);
    Route::get('/allappointment', [AdminAppointmentController::class, 'GetallAppointments']);
    Route::post('/services', [AdminAuthController::class, 'servicesAPIPOST']);
    Route::post('/services/', [AdminAuthController::class, 'servicesAPIPOST']);
    Route::get('/services/{id}', [AdminAuthController::class, 'servicesAPIgetbyid']);
    Route::get('/service/{id}', [AdminAuthController::class, 'getbyidShowService']);
    Route::get('/client/status-check', [AdminAuthController::class, 'autoStatusCheck']);
    Route::get('/clientpricestatus/{clientid}', [AdminAuthController::class, 'priceStatuseclientid']);

    // Admin Invoices & Payments
    Route::post('/proformainvoice', [AdminAuthController::class, 'proformaInvoiceAPIPOST']);
    Route::get('/proformainvoice', [AdminAuthController::class, 'proformaInvoiceAPIGet']);
    Route::post('/proformainvoicetakepayment/{id}', [AdminAuthController::class, 'proformaInvoicetakepaymentAPIPOST']);
    Route::post('/create-payment-intent', [AdminAuthController::class, 'paymentGetwaystripe']);

    // Admin Ratings & Reviews
    Route::post('/reviewsdoctor', [AdminRatingController::class, 'createReview']);
    Route::get('/reviewsdoctor', [AdminRatingController::class, 'getAllReviews']);
    Route::get('/reviewsdoctorcount', [AdminRatingController::class, 'getAverageRatings']);
    Route::get('/reviewsdoctor/doctor/{doctor_id}', [AdminRatingController::class, 'getDoctorReviews']);
    Route::delete('/reviewsdoctor/{id}', [AdminRatingController::class, 'deleteReview']);

    // Admin Coupons
    Route::post('/apply-coupon', [AdminCouponController::class, 'applyCoupon']);
});
