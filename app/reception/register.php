<?php
require_once '../config/config.php';

// Load departments and doctors for the form
 $departments = $conn->query("SELECT * FROM departments WHERE status = 1 ORDER BY name ASC");
 $doctors = $conn->query("
    SELECT d.id, d.name, d.department_id, dep.name as department_name 
    FROM doctors d 
    JOIN departments dep ON d.department_id = dep.id 
    WHERE d.status = 1 
    ORDER BY d.name ASC
");

// Build doctors array for Alpine.js
 $doctors_json = [];
while ($doc = $doctors->fetch_assoc()) {
    $doctors_json[] = $doc;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $patient_id_input = trim($_POST['patient_id'] ?? '');
    $patient_nin = trim($_POST['patient_nin'] ?? '');
    $patient_name = trim($_POST['patient_name'] ?? '');
    $patient_phone = trim($_POST['patient_phone'] ?? '');
    $patient_email = trim($_POST['patient_email'] ?? '');
    $patient_dob = trim($_POST['patient_dob'] ?? '');
    $patient_age = trim($_POST['patient_age'] ?? '');
    $patient_gender = trim($_POST['patient_gender'] ?? '');
    $patient_blood = trim($_POST['patient_blood'] ?? '');
    $patient_address = trim($_POST['patient_address'] ?? '');
    $emergency_name = trim($_POST['emergency_name'] ?? '');
    $emergency_phone = trim($_POST['emergency_phone'] ?? '');
    
    $department_id = (int)($_POST['department_id'] ?? 0);
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $is_new_patient = ($_POST['is_new_patient'] ?? '1') === '1';
    
    // Validation
    $errors = [];
    
    if (empty($patient_name)) $errors[] = "Patient name is required";
    if (empty($patient_phone)) $errors[] = "Phone number is required";
    if (empty($department_id)) $errors[] = "Department is required";
    
    if (!empty($errors)) {
        setFlash('error', implode('<br>', $errors));
        header("Location: register.php");
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Insert or get patient
        if ($is_new_patient) {
            $new_patient_id = generatePatientId($conn);
            $stmt = $conn->prepare("
                INSERT INTO patients (patient_id, patient_nin, name, phone, email, dob, age, gender, blood_group, address, emergency_contact_name, emergency_contact_phone)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $dob_val   = !empty($patient_dob) ? $patient_dob : null;
$blood_val = !empty($patient_blood) ? $patient_blood : null;

$stmt->bind_param("ssssssssssss", 
    $new_patient_id,
    $patient_nin,
    $patient_name,
    $patient_phone,
    $patient_email,
    $dob_val,
    $patient_age,
    $patient_gender,
    $blood_val,
    $patient_address,
    $emergency_name,
    $emergency_phone
);
            $stmt->execute();
            $db_patient_id = $conn->insert_id;
        } else {
            $db_patient_id = (int)$patient_id_input;
            // Update existing patient info
            $stmt = $conn->prepare("
                UPDATE patients SET 
                    patient_nin = ?, name = ?, phone = ?, email = ?, dob = ?, age = ?, 
                    gender = ?, blood_group = ?, address = ?, 
                    emergency_contact_name = ?, emergency_contact_phone = ?
                WHERE id = ?
            ");
            $dob_val   = !empty($patient_dob) ? $patient_dob : null;
$blood_val = !empty($patient_blood) ? $patient_blood : null;

$stmt->bind_param("sssssssssssi", 
    $patient_nin,
    $patient_name,
    $patient_phone,    
    $patient_email,
    $dob_val,
    $patient_age,
    $patient_gender,
    $blood_val,
    $patient_address,
    $emergency_name,
    $emergency_phone,
    $db_patient_id
);
            $stmt->execute();
            $new_patient_id = $conn->query("SELECT patient_id FROM patients WHERE id = $db_patient_id")->fetch_assoc()['patient_id'];
        }
        
        // Generate token
        $token = getTodayToken($conn, $department_id);
        
        // Create visit
        $visit_id = generateVisitId($conn);
        $stmt = $conn->prepare("
            INSERT INTO visits (visit_id, patient_id, department_id, doctor_id, token_number, visit_date, status)
            VALUES (?, ?, ?, ?, ?, CURDATE(), 'registered')
        ");
        $doc_val = $doctor_id ?: null;
        $stmt->bind_param("siiii", 
    $visit_id,
    $db_patient_id,
    $department_id,
    $doc_val,
    $token
);
        $stmt->execute();
        
        $conn->commit();
        
        setFlash('success', "Patient registered successfully!");
        header("Location: success.php?visit_id=" . urlencode($visit_id) . "&token=" . $token . "&new=" . ($is_new_patient ? '1' : '0'));
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        setFlash('error', "Registration failed: " . $e->getMessage());
        header("Location: register.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Register Patient | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>

<body class="text-base bg-body-bg text-body font-public dark:text-zink-100 dark:bg-zink-800 group-data-[skin=bordered]:bg-body-bordered group-data-[skin=bordered]:dark:bg-zink-700">
<div class="group-data-[sidebar-size=sm]:min-h-sm group-data-[sidebar-size=sm]:relative">

<?php include 'sidenav.php'; ?>
<?php include 'topnav.php'; ?>

<div class="relative min-h-screen group-data-[sidebar-size=sm]:min-h-sm">

    <div class="group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4 group-data-[navbar=bordered]:pt-[calc(theme('spacing.header')_*_1.3)] group-data-[navbar=hidden]:pt-0 group-data-[layout=horizontal]:mx-auto group-data-[layout=horizontal]:max-w-screen-2xl group-data-[layout=horizontal]:px-0 group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:ltr:md:ml-auto group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:rtl:md:mr-auto group-data-[layout=horizontal]:md:pt-[calc(theme('spacing.header')_*_1.6)] group-data-[layout=horizontal]:px-3 group-data-[layout=horizontal]:group-data-[navbar=hidden]:pt-[calc(theme('spacing.header')_*_0.9)]">
        <div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">

            <!-- Breadcrumb -->
            <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
                <div class="grow">
                    <h5 class="text-16">Register Patient</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Reception</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Register</li>
                </ul>
            </div>

            <!-- Flash Message -->
            <?php $flash = getFlash(); if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2">
                    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i>
                    <span><?= $flash['msg'] ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Registration Form - Alpine.js -->
            <form method="POST" action="register.php" x-data="registerForm()" x-cloak>

                <!-- Step 1: Search Patient -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex items-center justify-center size-10 rounded-full bg-custom-500 text-white font-bold text-sm shrink-0">1</div>
                            <div>
                                <h6 class="text-15 mb-0">Patient Lookup</h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Enter phone number to check if patient exists</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <div class="md:col-span-6">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Phone Number <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="tel" 
                                           x-model="searchPhone" 
                                           @input.debounce.400ms="searchPatient()" 
                                           class="ltr:pl-10 rtl:pr-10 form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200" 
                                           placeholder="01XXXXXXXXX" 
                                           maxlength="15"
                                           required>
                                    <i data-lucide="phone" class="inline-block size-4 absolute ltr:left-3 rtl:right-3 top-2.5 text-slate-400 dark:text-zink-200"></i>
                                </div>
                            </div>
                            <div class="md:col-span-3 flex items-end">
                                <button type="button" 
                                        @click="searchPatient()" 
                                        :disabled="searchPhone.length < 9 || searching"
                                        class="w-full px-4 py-[9px] text-white btn bg-custom-500 border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i data-lucide="search" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i>
                                    <span x-show="!searching">Search</span>
                                    <span x-show="searching">Searching...</span>
                                </button>
                            </div>
                            <div class="md:col-span-3 flex items-end">
                                <!-- Result indicator -->
                                <div x-show="searchDone && !patientFound" class="flex items-center gap-2 p-2 rounded-md bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 w-full">
                                    <i data-lucide="user-plus" class="size-5 text-green-500 shrink-0"></i>
                                    <span class="text-sm text-green-600 dark:text-green-400 font-medium">New Patient</span>
                                </div>
                                <div x-show="searchDone && patientFound" class="flex items-center gap-2 p-2 rounded-md bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20 w-full">
                                    <i data-lucide="user-check" class="size-5 text-sky-500 shrink-0"></i>
                                    <span class="text-sm text-sky-600 dark:text-sky-400 font-medium">Returning Patient</span>
                                    <span class="text-xs text-slate-500 dark:text-zink-200">(<span x-text="visit_count"></span> visits)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Returning patient info preview -->
                        <div x-show="patientFound" x-transition class="mt-4 p-4 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                            <div class="flex items-center gap-4">
                                <div class="flex items-center justify-center size-14 rounded-full bg-custom-100 dark:bg-custom-500/20 shrink-0">
                                    <i data-lucide="user" class="size-7 text-custom-500"></i>
                                </div>
                                <div class="grow">
                                    <h5 class="text-base font-semibold" x-text="patient.name"></h5>
                                    <p class="text-sm text-slate-500 dark:text-zink-200">
                                        <span x-text="patient.patient_id"></span> &middot; 
                                        <span x-text="patient.age"></span>y &middot; 
                                        <span x-text="patient.gender"></span> &middot;
                                        <span x-text="patient.blood_group || 'N/A'"></span>
                                    </p>
                                    <p class="text-xs text-slate-400 dark:text-zink-300" x-text="patient.address || 'No address on file'"></p>
                                </div>
                                <button type="button" @click="resetSearch()" class="flex items-center justify-center size-8 rounded-md bg-white dark:bg-zink-700 border border-slate-200 dark:border-zink-500 text-slate-500 hover:text-red-500 dark:hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all">
                                    <i data-lucide="x" class="size-4"></i>
                                </button>
                            </div>
                        </div>

                        <input type="hidden" name="is_new_patient" :value="patientFound ? '0' : '1'">
                        <input type="hidden" name="patient_id" :value="patient.id || ''">
                    </div>
                </div>

                <!-- Step 2: Patient Details -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex items-center justify-center size-10 rounded-full bg-custom-500 text-white font-bold text-sm shrink-0">2</div>
                            <div>
                                <h6 class="text-15 mb-0">Patient Details</h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200" x-show="!patientFound">Fill in the new patient information</p>
                                <p class="text-xs text-slate-500 dark:text-zink-200" x-show="patientFound">Verify or update existing information</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <!-- Name -->
                            <div class="md:col-span-4">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" name="patient_name" x-model="patient.name" required
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                       placeholder="Enter full name">
                            </div>
                            <!-- Phone -->
                            <div class="md:col-span-4">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Phone <span class="text-red-500">*</span></label>
                                <input type="tel" name="patient_phone" x-model="patient.phone" required
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                       placeholder="01XXXXXXXXX" maxlength="11">
                            </div>
                            <!-- NIN -->
                            <div class="md:col-span-4">
                                <label class="block mb-1.5 text-uppercase font-medium text-slate-700 dark:text-zink-200">NIN <span class="text-red-500">*</span></label>
                                <input type="text" name="patient_nin" x-model="patient.nin" required
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                       placeholder="CM**********" maxlength="14">
                            </div>
                            <!-- DOB -->
                            <div class="md:col-span-3">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Date of Birth</label>
                                <input type="date" name="patient_dob" x-model="patient.dob" @change="calcAge()"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                            </div>
                            <!-- Age -->
                            <div class="md:col-span-3">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Age</label>
                                <input type="text" name="patient_age" x-model="patient.age"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800"
                                       placeholder="e.g. 35">
                            </div>
                            <!-- Gender -->
                            <div class="md:col-span-3">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Gender <span class="text-red-500">*</span></label>
                                <select name="patient_gender" x-model="patient.gender" required
                                        class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <!-- Blood Group -->
                            <div class="md:col-span-3">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Blood Group</label>
                                <select name="patient_blood" x-model="patient.blood_group"
                                        class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                                    <option value="">Select</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <!-- Address -->
                            <div class="md:col-span-3">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Address</label>
                                <input type="text" name="patient_address" x-model="patient.address"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                       placeholder="Full address">
                            </div>
                            <!-- Email -->
                            <div class="md:col-span-3">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Email</label>
                                <input type="email" name="patient_email" x-model="patient.email"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                       placeholder="optional@email.com">
                            </div>
                            <!-- Emergency Contact Name -->
                            <div class="md:col-span-3">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Emergency Contact Name</label>
                                <input type="text" name="emergency_name" x-model="patient.emergency_contact_name"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                       placeholder="Guardian / relative">
                            </div>
                            <!-- Emergency Contact Phone -->
                            <div class="md:col-span-3">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Emergency Contact Phone</label>
                                <input type="tel" name="emergency_phone" x-model="patient.emergency_contact_phone"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                       placeholder="01XXXXXXXXX" maxlength="11">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Visit Details -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex items-center justify-center size-10 rounded-full bg-custom-500 text-white font-bold text-sm shrink-0">3</div>
                            <div>
                                <h6 class="text-15 mb-0">Visit Details</h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Assign department and doctor</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <!-- Department -->
                            <div class="md:col-span-4">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Department <span class="text-red-500">*</span></label>
                                <select name="department_id" x-model="selectedDepartment" required
                                        class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                                    <option value="">Select Department</option>
                                    <?php $departments->data_seek(0); while ($dept = $departments->fetch_assoc()): ?>
                                    <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <!-- Doctor -->
                            <div class="md:col-span-4">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Doctor</label>
                                <select name="doctor_id" x-model="selectedDoctor"
                                        class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800"
                                        :disabled="!selectedDepartment">
                                    <option value="">Select Doctor</option>
                                    <template x-for="doc in filteredDoctors" :key="doc.id">
                                        <option :value="doc.id" x-text="doc.name + ' (' + doc.department_name + ')'"></option>
                                    </template>
                                </select>
                                <p class="text-xs text-slate-400 dark:text-zink-300 mt-1" x-show="selectedDepartment && filteredDoctors.length === 0">No doctors in this department</p>
                            </div>
                            <!-- Token Preview -->
                            <div class="md:col-span-4">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Token Number</label>
                                <div class="flex items-center gap-3 px-3 py-[10px] rounded-md bg-slate-100 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                                    <span class="text-lg font-bold text-custom-500" x-show="selectedDepartment">
                                        <span x-text="'#' + String(getToken()).padStart(3, '0')"></span>
                                    </span>
                                    <span class="text-slate-400 dark:text-zink-300" x-show="!selectedDepartment">Select department first</span>
                                </div>
                                <input type="hidden" name="token_number" :value="getToken()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit" 
                            class="px-6 py-2.5 text-white btn bg-custom-500 border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 focus:text-white focus:bg-custom-600 focus:border-custom-600">
                        <i data-lucide="check" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i>
                        Register Patient
                    </button>
                    <a href="index.php" class="px-6 py-2.5 text-slate-500 btn bg-white border-slate-200 hover:text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:text-zink-100 dark:hover:bg-zink-600">
                        Cancel
                    </a>
                </div>

            </form>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
    function registerForm() {
        return {
            // Search state
            searchPhone: '',
            searching: false,
            searchDone: false,
            patientFound: false,
            visit_count: 0,

            // Patient data
            patient: {
                id: '',
                patient_id: '',
                name: '',
                phone: '',
                email: '',
                dob: '',
                age: '',
                gender: '',
                blood_group: '',
                address: '',
                emergency_contact_name: '',
                emergency_contact_phone: ''
            },

            // Visit data
            selectedDepartment: '',
            selectedDoctor: '',

            // Doctors from PHP
            allDoctors: <?= json_encode($doctors_json) ?>,

            // Filtered doctors by department
            get filteredDoctors() {
                if (!this.selectedDepartment) return [];
                return this.allDoctors.filter(d => d.department_id == this.selectedDepartment);
            },

            // Simulate token (actual token generated server-side)
            getToken() {
                if (!this.selectedDepartment) return 0;
                const deptDocs = this.filteredDoctors;
                // This is just UI preview, server generates the real token
                return Math.floor(Math.random() * 50) + 1;
            },

            // Search patient by phone
            async searchPatient() {
                if (this.searchPhone.length < 9) return;

                this.searching = true;
                this.searchDone = false;

                try {
                    const response = await fetch('api_search.php?phone=' + encodeURIComponent(this.searchPhone));
                    const data = await response.json();

                    this.searchDone = true;
                    this.patientFound = data.found;

                    if (data.found) {
                        this.patient = { ...this.patient, ...data.patient };
                        this.visit_count = data.visit_count || 0;
                    } else {
                        this.resetPatientFields();
                        // Auto-fill phone from search
                        this.patient.phone = this.searchPhone;
                    }
                } catch (e) {
                    console.error('Search failed:', e);
                    this.searchDone = true;
                }

                this.searching = false;

                // Re-init Lucide for any new icons
                if (typeof lucide !== 'undefined') lucide.createIcons();
            },

            // Reset search
            resetSearch() {
                this.searchPhone = '';
                this.searchDone = false;
                this.patientFound = false;
                this.visit_count = 0;
                this.resetPatientFields();
            },

            // Reset patient fields
            resetPatientFields() {
                this.patient = {
                    id: '', patient_id: '', name: '', phone: this.searchPhone,
                    email: '', dob: '', age: '', gender: '', blood_group: '',
                    address: '', emergency_contact_name: '', emergency_contact_phone: ''
                };
            },

            // Calculate age from DOB
            calcAge() {
                if (!this.patient.dob) return;
                const dob = new Date(this.patient.dob);
                const today = new Date();
                let age = today.getFullYear() - dob.getFullYear();
                const m = today.getMonth() - dob.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
                this.patient.age = age + ' years';
            }
        };
    }
    </script>

</body>
</html>