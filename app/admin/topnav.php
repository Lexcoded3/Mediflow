<?php

$current_user = currentUser();
$role = $current_user['role'] ?? '';

$notifications = [];
$search_results = [];

$q = trim($_GET['topnav_q'] ?? '');

/* -------------------------------------------------
   SEARCH (Patients + Visits)
-------------------------------------------------- */
if ($q && strlen($q) >= 2) {

    $like = '%' . $conn->real_escape_string($q) . '%';

    // Patients search
    $patients_sql = "
        SELECT patient_id, name, phone
        FROM patients
        WHERE name LIKE '$like'
           OR patient_id LIKE '$like'
           OR phone LIKE '$like'
        LIMIT 5
    ";
    $search_results['patients'] = $conn->query($patients_sql);

    if (!$search_results['patients']) {
        die("Patients search failed: " . $conn->error);
    }

    // Visits search (FIXED precedence bug)
    $visits_sql = "
        SELECT v.visit_id, v.status, v.token_number, p.name AS patient_name
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        WHERE (v.visit_id LIKE '$like'
           OR p.name LIKE '$like')
        AND v.visit_date = CURDATE()
        LIMIT 5
    ";

    $search_results['visits'] = $conn->query($visits_sql);

    if (!$search_results['visits']) {
        die("Visits search failed: " . $conn->error);
    }
}

/* -------------------------------------------------
   RECEPTION / ADMIN: New visits
-------------------------------------------------- */
if (in_array($role, ['admin', 'receptionist'])) {

    $sql = "
        SELECT p.name, v.visit_id, v.token_number, v.created_at
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        WHERE v.visit_date = CURDATE()
          AND v.status = 'registered'
        ORDER BY v.created_at DESC
        LIMIT 10
    ";

    $res = $conn->query($sql);

    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $notifications[] = [
                'icon'  => 'clipboard-check',
                'color' => 'bg-blue-100 text-blue-500',
                'title' => "New patient: <b>{$r['name']}</b>",
                'sub'   => "Token #{$r['token_number']} · {$r['visit_id']}",
                'time'  => $r['created_at'],
            ];
        }
    }
}

/* -------------------------------------------------
   TRIAGE
-------------------------------------------------- */
if (in_array($role, ['admin', 'triage_nurse'])) {

    $sql = "
        SELECT p.name, v.visit_id, v.token_number, v.created_at
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        WHERE v.visit_date = CURDATE()
          AND v.status = 'registered'
        ORDER BY v.created_at ASC
        LIMIT 10
    ";

    $res = $conn->query($sql);

    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $notifications[] = [
                'icon'  => 'heart-pulse',
                'color' => 'bg-red-100 text-red-500',
                'title' => "<b>{$r['name']}</b> waiting for triage",
                'sub'   => "Token #{$r['token_number']}",
                'time'  => $r['created_at'],
            ];
        }
    }
}

/* -------------------------------------------------
   DOCTOR
-------------------------------------------------- */
if (in_array($role, ['admin', 'doctor'])) {

    $sql = "
        SELECT p.name, v.visit_id, v.token_number, d.name AS dept, v.updated_at
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        JOIN departments d ON d.id = v.department_id
        WHERE v.visit_date = CURDATE()
          AND v.status = 'triage'
        ORDER BY v.updated_at ASC
        LIMIT 10
    ";

    $res = $conn->query($sql);

    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $notifications[] = [
                'icon'  => 'stethoscope',
                'color' => 'bg-green-100 text-green-500',
                'title' => "<b>{$r['name']}</b> ready for consultation",
                'sub'   => "{$r['dept']} · Token #{$r['token_number']}",
                'time'  => $r['updated_at'],
            ];
        }
    }
}

/* -------------------------------------------------
   LAB
-------------------------------------------------- */
if (in_array($role, ['admin', 'lab_technician'])) {
    $res = $conn->query("
        SELECT p.name, v.visit_id, lo.ordered_at
        FROM lab_orders lo
        JOIN visits v ON v.id = lo.visit_id
        JOIN patients p ON p.id = v.patient_id
        WHERE v.visit_date = CURDATE()
          AND v.status = 'lab'
        ORDER BY lo.ordered_at ASC
        LIMIT 10
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $notifications[] = [
                'icon'  => 'test-tube',
                'color' => 'bg-sky-100 text-sky-500',
                'title' => "Lab order pending: <b>{$r['name']}</b>",
                'sub'   => $r['visit_id'],
                'time'  => $r['ordered_at'],
            ];
        }
    }
}

/* -------------------------------------------------
   PHARMACY
-------------------------------------------------- */
if (in_array($role, ['admin', 'pharmacist'])) {

    $sql = "
        SELECT p.name, v.visit_id, pr.created_at
        FROM prescriptions pr
        JOIN consultations c ON c.id = pr.consultation_id
        JOIN visits v ON v.id = c.visit_id
        JOIN patients p ON p.id = v.patient_id
        WHERE v.visit_date = CURDATE()
          AND v.status = 'pharmacy'
        ORDER BY pr.created_at ASC
        LIMIT 10
    ";

    $res = $conn->query($sql);

    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $notifications[] = [
                'icon'  => 'pill',
                'color' => 'bg-orange-100 text-orange-500',
                'title' => "Prescription pending: <b>{$r['name']}</b>",
                'sub'   => $r['visit_id'],
                'time'  => $r['created_at'],
            ];
        }
    }
}

/* -------------------------------------------------
   BILLING
-------------------------------------------------- */
if (in_array($role, ['admin', 'billing'])) {

    $sql = "
        SELECT p.name, v.visit_id, v.updated_at
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        LEFT JOIN bills b ON b.visit_id = v.id
        WHERE v.visit_date = CURDATE()
          AND v.status IN ('completed','pharmacy')
          AND b.id IS NULL
        ORDER BY v.updated_at DESC
        LIMIT 10
    ";

    $res = $conn->query($sql);

    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $notifications[] = [
                'icon'  => 'receipt',
                'color' => 'bg-yellow-100 text-yellow-500',
                'title' => "Unpaid bill: <b>{$r['name']}</b>",
                'sub'   => $r['visit_id'],
                'time'  => $r['updated_at'],
            ];
        }
    }
}

/* -------------------------------------------------
   COUNT
-------------------------------------------------- */
$notif_count = count($notifications);

/* -------------------------------------------------
   TIME AGO HELPER
-------------------------------------------------- */
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);

    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';

    return date('d M', strtotime($datetime));
}

?>
<!-- Left Sidebar End -->
    <div id="sidebar-overlay" class="absolute inset-0 z-[1002] bg-slate-500/30 hidden">   
    </div>
    <header id="page-topbar" class="ltr:md:left-vertical-menu rtl:md:right-vertical-menu group-data-[sidebar-size=md]:ltr:md:left-vertical-menu-md group-data-[sidebar-size=md]:rtl:md:right-vertical-menu-md group-data-[sidebar-size=sm]:ltr:md:left-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:md:right-vertical-menu-sm group-data-[layout=horizontal]:ltr:left-0 group-data-[layout=horizontal]:rtl:right-0 fixed right-0 z-[1000] left-0 print:hidden group-data-[navbar=bordered]:m-4 group-data-[navbar=bordered]:[&.is-sticky]:mt-0 transition-all ease-linear duration-300 group-data-[navbar=hidden]:hidden group-data-[navbar=scroll]:absolute group/topbar group-data-[layout=horizontal]:z-[1004]">
        <div class="layout-width">
            <div class="flex items-center px-4 mx-auto bg-topbar border-b-2 border-topbar group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:border-topbar-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:border-topbar-brand shadow-md h-header shadow-slate-200/50 group-data-[navbar=bordered]:rounded-md group-data-[navbar=bordered]:group-[.is-sticky]/topbar:rounded-t-none group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:border-zink-700 dark:shadow-none group-data-[topbar=dark]:group-[.is-sticky]/topbar:dark:shadow-zink-500 group-data-[topbar=dark]:group-[.is-sticky]/topbar:dark:shadow-md group-data-[navbar=bordered]:shadow-none group-data-[layout=horizontal]:group-data-[navbar=bordered]:rounded-b-none group-data-[layout=horizontal]:shadow-none group-data-[layout=horizontal]:dark:group-[.is-sticky]/topbar:shadow-none">
                <div class="flex items-center w-full group-data-[layout=horizontal]:mx-auto group-data-[layout=horizontal]:max-w-screen-2xl navbar-header group-data-[layout=horizontal]:ltr:xl:pr-3 group-data-[layout=horizontal]:rtl:xl:pl-3">
                    <!-- LOGO -->
                    <div class="items-center justify-center hidden px-5 text-center h-header group-data-[layout=horizontal]:md:flex group-data-[layout=horizontal]:ltr::pl-0 group-data-[layout=horizontal]:rtl:pr-0">
                        <a href="index.php">
                            <span class="hidden">
                                <img src="../assets/images/logo.png" alt="" class="h-6 mx-auto">
                            </span>
                            <span class="group-data-[topbar=dark]:hidden group-data-[topbar=brand]:hidden">
                                <img src="../assets/images/logo-dark.png" alt="" class="h-6 mx-auto">
                            </span>
                        </a>
                        <a href="index.php" class="hidden group-data-[topbar=dark]:block group-data-[topbar=brand]:block">
                            <span class="group-data-[topbar=dark]:hidden group-data-[topbar=brand]:hidden">
                                <img src="../assets/images/logo.png" alt="" class="h-6 mx-auto">
                            </span>
                            <span class="group-data-[topbar=dark]:block group-data-[topbar=brand]:block">
                                <img src="../assets/images/logo-light.png" alt="" class="h-6 mx-auto">
                            </span>
                        </a>
                    </div>
    
                    <button type="button" class="inline-flex relative justify-center items-center p-0 text-topbar-item transition-all w-[37.5px] h-[37.5px] duration-75 ease-linear bg-topbar rounded-md btn hover:bg-slate-100 group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:border-topbar-dark group-data-[topbar=dark]:text-topbar-item-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:border-topbar-brand group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:text-zink-200 group-data-[topbar=dark]:dark:border-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=dark]:dark:hover:text-zink-50 group-data-[layout=horizontal]:flex group-data-[layout=horizontal]:md:hidden hamburger-icon" id="topnav-hamburger-icon">
                        <i data-lucide="chevrons-left" class="w-5 h-5 group-data-[sidebar-size=sm]:hidden"></i>
                        <i data-lucide="chevrons-right" class="hidden w-5 h-5 group-data-[sidebar-size=sm]:block"></i>
                    </button>
    
                    <!-- Search -->
    <div class="relative hidden ltr:ml-3 rtl:mr-3 lg:block" x-data="{ open: false, q: '' }">
        <input type="text" x-model="q"
               @input.debounce.300ms="open = q.length >= 2"
               @keydown.escape="open = false"
               @click.outside="open = false"
               class="py-2 pr-4 text-sm bg-topbar border border-topbar-border rounded pl-8 placeholder:text-slate-400 form-control focus-visible:outline-0 min-w-[300px] focus:border-blue-400 group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:border-topbar-border-dark group-data-[topbar=dark]:text-topbar-item-dark group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:border-zink-500 group-data-[topbar=dark]:dark:text-zink-100"
               placeholder="Search patients, visits..." autocomplete="off"
               @input.debounce.300ms="if(q.length>=2){ $el.form && $el.form.submit(); window.location='?topnav_q='+encodeURIComponent(q) }">
        <i data-lucide="search" class="inline-block size-4 absolute left-2.5 top-2.5 text-slate-400"></i>

        <?php if ($q && ($search_results['patients']->num_rows > 0 || $search_results['visits']->num_rows > 0)): ?>
        <div class="absolute top-full ltr:left-0 mt-1 w-[420px] bg-white dark:bg-zink-600 rounded-md shadow-lg border border-slate-200 dark:border-zink-500 z-50 max-h-[400px] overflow-y-auto">

            <?php if ($search_results['patients']->num_rows > 0): ?>
            <div class="px-3 py-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100 dark:border-zink-500">Patients</div>
            <?php while ($p = $search_results['patients']->fetch_assoc()): ?>
            <a href="../reception/patient.php?id=<?= $p['patient_id'] ?>" class="flex items-center gap-3 px-3 py-2.5 hover:bg-slate-50 dark:hover:bg-zink-500">
                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-500 text-sm font-bold shrink-0">
                    <?= strtoupper(substr($p['name'], 0, 1)) ?>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-700 dark:text-zink-100"><?= e($p['name']) ?></p>
                    <p class="text-xs text-slate-400"><?= e($p['patient_id']) ?> · <?= e($p['phone'] ?: 'No phone') ?></p>
                </div>
            </a>
            <?php endwhile; ?>
            <?php endif; ?>

            <?php if ($search_results['visits']->num_rows > 0): ?>
            <div class="px-3 py-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100 dark:border-zink-500 <?= $search_results['patients']->num_rows > 0 ? 'border-t mt-1' : '' ?>">Today's Visits</div>
            <?php while ($v = $search_results['visits']->fetch_assoc()): ?>
            <a href="../track.php?visit_id=<?= $v['visit_id'] ?>" class="flex items-center gap-3 px-3 py-2.5 hover:bg-slate-50 dark:hover:bg-zink-500">
                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-green-100 text-green-500 shrink-0">
                    <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                </div>
                <div class="grow">
                    <p class="text-sm font-medium text-slate-700 dark:text-zink-100"><?= e($v['visit_id']) ?></p>
                    <p class="text-xs text-slate-400"><?= e($v['patient_name']) ?> · Token #<?= $v['token_number'] ?></p>
                </div>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-zink-500 text-slate-500 dark:text-zink-300 capitalize"><?= $v['status'] ?></span>
            </a>
            <?php endwhile; ?>
            <?php endif; ?>

        </div>
        <?php elseif ($q && strlen($q) >= 2): ?>
        <div class="absolute top-full ltr:left-0 mt-1 w-[300px] bg-white dark:bg-zink-600 rounded-md shadow-lg border border-slate-200 dark:border-zink-500 z-50 px-4 py-6 text-center">
            <i data-lucide="search-x" class="w-8 h-8 mx-auto text-slate-300 mb-2"></i>
            <p class="text-sm text-slate-400">No results for "<?= e($q) ?>"</p>
        </div>
        <?php endif; ?>
    </div>
    
                    <div class="flex gap-3 ms-auto">
                        <div class="relative flex items-center dropdown h-header">
                            <button type="button" class="inline-flex justify-center items-center p-0 text-topbar-item transition-all w-[37.5px] h-[37.5px] duration-200 ease-linear bg-topbar rounded-md dropdown-toggle btn hover:bg-topbar-item-bg-hover hover:text-topbar-item-hover group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=dark]:dark:text-zink-500 group-data-[topbar=dark]:dark:hover:text-zink-50" id="flagsDropdown" data-bs-toggle="dropdown">
                                <img src="../assets/images/us.svg" alt="" id="header-lang-img" class="h-5 rounded-sm">
                            </button>
                            <div class="absolute z-50 hidden p-4 ltr:text-left rtl:text-right bg-white rounded-md shadow-md !top-4 dropdown-menu min-w-[10rem] flex flex-col gap-4 dark:bg-zink-600" aria-labelledby="flagsDropdown">
                                <a href="#!" class="flex items-center gap-3 group/items language" data-lang="en" title="English">
                                    <img src="../assets/images/us.svg" alt="" class="object-cover h-4 rounded-full">
                                    <h6 class="transition-all duration-200 ease-linear font-15medium text- text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">English</h6>
                                </a>
                                <a href="#!" class="flex items-center gap-3 group/items language" data-lang="sp" title="Spanish">
                                    <img src="../assets/images/es.svg" alt="" class="object-cover h-4 rounded-full">
                                    <h6 class="transition-all duration-200 ease-linear font-15medium text- text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">Spanish</h6>
                                </a>
                                <a href="#!" class="flex items-center gap-3 group/items language" data-lang="gr" title="German">
                                    <img src="../assets/images/de.svg" alt="" class="object-cover h-4 rounded-full">
                                    <h6 class="transition-all duration-200 ease-linear font-15medium text- text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">German</h6>
                                </a>
                                <a href="#!" class="flex items-center gap-3 group/items language" data-lang="fr" title="French">
                                    <img src="../assets/images/fr.svg" alt="" class="object-cover h-4 rounded-full">
                                    <h6 class="transition-all duration-200 ease-linear font-15medium text- text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">French</h6>
                                </a>
                                <a href="#!" class="flex items-center gap-3 group/items language" data-lang="jp" title="Japanese">
                                    <img src="../assets/images/jp.svg" alt="" class="object-cover h-4 rounded-full">
                                    <h6 class="transition-all duration-200 ease-linear font-15medium text- text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">Japanese</h6>
                                </a>
                                <a href="#!" class="flex items-center gap-3 group/items language" data-lang="ch" title="Chinese">
                                    <img src="../assets/images/china.svg" alt="" class="object-cover h-4 rounded-full">
                                    <h6 class="transition-all duration-200 ease-linear font-15medium text- text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">Chinese</h6>
                                </a>
                                <a href="#!" class="flex items-center gap-3 group/items language" data-lang="it" title="Italian">
                                    <img src="../assets/images/it2.svg" alt="" class="object-cover h-4 rounded-full">
                                    <h6 class="transition-all duration-200 ease-linear font-15medium text- text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">Italian</h6>
                                </a>
                                <a href="#!" class="flex items-center gap-3 group/items language" data-lang="ru" title="Russian">
                                    <img src="../assets/images/ru2.svg" alt="" class="object-cover h-4 rounded-full">
                                    <h6 class="transition-all duration-200 ease-linear font-15medium text- text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">Russian</h6>
                                </a>
                                <a href="#!" class="flex items-center gap-3 group/items language" data-lang="ar" title="Arabic">
                                    <img src="../assets/images/ae2.svg" alt="" class="object-cover h-4 rounded-full">
                                    <h6 class="transition-all duration-200 ease-linear font-15medium text- text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">Arabic</h6>
                                </a>
                            </div>
                        </div>
    
                        <div class="relative flex items-center h-header">
                            <button type="button" class="inline-flex relative justify-center items-center p-0 text-topbar-item transition-all w-[37.5px] h-[37.5px] duration-200 ease-linear bg-topbar rounded-md btn hover:bg-topbar-item-bg-hover hover:text-topbar-item-hover group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=dark]:dark:hover:text-zink-50 group-data-[topbar=dark]:dark:text-zink-200 group-data-[topbar=dark]:text-topbar-item-dark" id="light-dark-mode">
                                <i data-lucide="sun" class="inline-block w-5 h-5 stroke-1 fill-slate-100 group-data-[topbar=dark]:fill-topbar-item-bg-hover-dark group-data-[topbar=brand]:fill-topbar-item-bg-hover-brand"></i>
                            </button>
                        </div>
    
                        <div class="relative flex items-center dropdown h-header">
                            <button type="button" class="inline-flex justify-center relative items-center p-0 text-topbar-item transition-all w-[37.5px] h-[37.5px] duration-200 ease-linear bg-topbar rounded-md dropdown-toggle btn hover:bg-topbar-item-bg-hover hover:text-topbar-item-hover group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=dark]:dark:hover:text-zink-50 group-data-[topbar=dark]:dark:text-zink-200 group-data-[topbar=dark]:text-topbar-item-dark" id="notificationDropdown" data-bs-toggle="dropdown">
                                <i data-lucide="bell-ring" class="inline-block w-5 h-5 stroke-1 fill-slate-100 group-data-[topbar=dark]:fill-topbar-item-bg-hover-dark group-data-[topbar=brand]:fill-topbar-item-bg-hover-brand"></i>
                                <?php if ($notif_count > 0): ?>
                                <span class="absolute top-0 right-0 flex w-1.5 h-1.5">
                                    <span class="absolute inline-flex w-full h-full rounded-full opacity-75 animate-ping bg-red-400"></span>
                                    <span class="relative inline-flex w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                </span>
                                <?php endif; ?>
                            </button>
                            <div class="absolute z-50 hidden ltr:text-left rtl:text-right bg-white rounded-md shadow-md !top-4 dropdown-menu min-w-[20rem] lg:min-w-[26rem] dark:bg-zink-600" aria-labelledby="notificationDropdown">
                                <div class="p-4">
                                    <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-zink-500">
                                        <h6 class="text-sm font-semibold">
                                            Notifications
                                            <?php if ($notif_count > 0): ?>
                                            <span class="inline-flex items-center justify-center w-5 h-5 ml-1 text-[11px] font-medium rounded-full text-white bg-red-500"><?= min($notif_count, 99) ?></span>
                                            <?php endif; ?>
                                        </h6>
                                        <span class="text-xs text-slate-400 dark:text-zink-300"><?= ROLE_NAMES[$role] ?? '' ?></span>
                                    </div>
                                    <!-- <ul class="flex flex-wrap w-full p-1 mb-2 text-sm font-medium text-center rounded-md filter-btns text-slate-500 bg-slate-100 nav-tabs dark:bg-zink-500 dark:text-zink-200" data-filter-target="notification-list">
                                        <li class="grow">
                                            <a href="javascript:void(0);" data-filter="all" class="inline-block nav-link px-1.5 w-full py-1 text-xs transition-all duration-300 ease-linear rounded-md text-slate-500 border border-transparent [&.active]:bg-white [&.active]:text-custom-500 hover:text-custom-500 active:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:[&.active]:bg-zink-600 -mb-[1px] active">View All</a>
                                        </li>
                                        <li class="grow">
                                            <a href="javascript:void(0);" data-filter="mention" class="inline-block nav-link px-1.5 w-full py-1 text-xs transition-all duration-300 ease-linear rounded-md text-slate-500 border border-transparent [&.active]:bg-white [&.active]:text-custom-500 hover:text-custom-500 active:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:[&.active]:bg-zink-600 -mb-[1px]">Mentions</a>
                                        </li>
                                        <li class="grow">
                                            <a href="javascript:void(0);" data-filter="follower" class="inline-block nav-link px-1.5 w-full py-1 text-xs transition-all duration-300 ease-linear rounded-md text-slate-500 border border-transparent [&.active]:bg-white [&.active]:text-custom-500 hover:text-custom-500 active:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:[&.active]:bg-zink-600 -mb-[1px]">Followers</a>
                                        </li>
                                        <li class="grow">
                                            <a href="javascript:void(0);" data-filter="invite" class="inline-block nav-link px-1.5 w-full py-1 text-xs transition-all duration-300 ease-linear rounded-md text-slate-500 border border-transparent [&.active]:bg-white [&.active]:text-custom-500 hover:text-custom-500 active:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:[&.active]:bg-zink-600 -mb-[1px]">Invites</a>
                                        </li>
                                    </ul> -->
    
                                </div>
                                <!-- <div data-simplebar="" class="max-h-[350px]">
                                    <div class="flex flex-col gap-1" id="notification-list">
                                        <a href="#!" class="flex gap-3 p-4 product-item hover:bg-slate-50 dark:hover:bg-zink-500 follower">
                                            <div class="w-10 h-10 rounded-md shrink-0 bg-slate-100">
                                                <img src="../assets/images/avatar-3.png" alt="" class="rounded-md">
                                            </div>
                                            <div class="grow">
                                                <h6 class="mb-1 font-medium"><b>@willie_passem</b> followed you</h6>
                                                <p class="mb-0 text-sm text-slate-500 dark:text-zink-300"><i data-lucide="clock" class="inline-block w-3.5 h-3.5 mr-1"></i> <span class="align-middle">Wednesday 03:42 PM</span></p>
                                            </div>
                                            <div class="flex items-center self-start gap-2 text-xs text-slate-500 shrink-0 dark:text-zink-300">
                                                <div class="w-1.5 h-1.5 bg-custom-500 rounded-full"></div> 4 sec
                                            </div>
                                        </a>
                                        <a href="#!" class="flex gap-3 p-4 product-item hover:bg-slate-50 dark:hover:bg-zink-500 mention">
                                            <div class="w-10 h-10 bg-yellow-100 rounded-md shrink-0">
                                                <img src="../assets/images/avatar-5.png" alt="" class="rounded-md">
                                            </div>
                                            <div class="grow">
                                                <h6 class="mb-1 font-medium"><b>@caroline_jessica</b> commented on your post</h6>
                                                <p class="mb-3 text-sm text-slate-500 dark:text-zink-300"><i data-lucide="clock" class="inline-block w-3.5 h-3.5 mr-1"></i> <span class="align-middle">Wednesday 03:42 PM</span></p>
                                                <div class="p-2 rounded bg-slate-100 text-slate-500 dark:bg-zink-500 dark:text-zink-300">Amazing! Fast, to the point, professional and really amazing to work with them!!!</div>
                                            </div>
                                            <div class="flex items-center self-start gap-2 text-xs text-slate-500 shrink-0 dark:text-zink-300">
                                                <div class="w-1.5 h-1.5 bg-custom-500 rounded-full"></div> 15 min
                                            </div>
                                        </a>
                                        <a href="#!" class="flex gap-3 p-4 product-item hover:bg-slate-50 dark:hover:bg-zink-500 invite">
                                            <div class="flex items-center justify-center w-10 h-10 bg-red-100 rounded-md shrink-0">
                                                <i data-lucide="shopping-bag" class="w-5 h-5 text-red-500 fill-red-200"></i>
                                            </div>
                                            <div class="grow">
                                                <h6 class="mb-1 font-medium">Successfully purchased a business plan for <span class="text-red-500">$199.99</span></h6>
                                                <p class="mb-0 text-sm text-slate-500 dark:text-zink-300"><i data-lucide="clock" class="inline-block w-3.5 h-3.5 mr-1"></i> <span class="align-middle">Monday 11:26 AM</span></p>
                                            </div>
                                            <div class="flex items-center self-start gap-2 text-xs text-slate-500 shrink-0 dark:text-zink-300">
                                                <div class="w-1.5 h-1.5 bg-custom-500 rounded-full"></div> Yesterday
                                            </div>
                                        </a>
                                        <a href="#!" class="flex gap-3 p-4 product-item hover:bg-slate-50 dark:hover:bg-zink-500 mention">
                                            <div class="relative shrink-0">
                                                <div class="w-10 h-10 bg-pink-100 rounded-md">
                                                    <img src="../assets/images/avatar-7.png" alt="" class="rounded-md">
                                                </div>
                                                <div class="absolute text-orange-500 -bottom-0.5 -right-0.5 text-16">
                                                    <i class="ri-heart-fill"></i>
                                                </div>
                                            </div>
                                            <div class="grow">
                                                <h6 class="mb-1 font-medium"><b>@scott</b> liked your post</h6>
                                                <p class="mb-0 text-sm text-slate-500 dark:text-zink-300"><i data-lucide="clock" class="inline-block w-3.5 h-3.5 mr-1"></i> <span class="align-middle">Thursday 06:59 AM</span></p>
                                            </div>
                                            <div class="flex items-center self-start gap-2 text-xs text-slate-500 shrink-0 dark:text-zink-300">
                                                <div class="w-1.5 h-1.5 bg-custom-500 rounded-full"></div> 1 Week
                                            </div>
                                        </a>
                                    </div>
                                </div> -->
                                <div class="max-h-[360px] overflow-y-auto">
                    <?php if (empty($notifications)): ?>
                    <div class="flex flex-col items-center justify-center py-10 text-center px-4">
                        <i data-lucide="bell-off" class="w-10 h-10 text-slate-200 dark:text-zink-500 mb-3"></i>
                        <p class="text-sm text-slate-400 dark:text-zink-300">All clear — no pending items</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                    <div class="flex gap-3 p-4 hover:bg-slate-50 dark:hover:bg-zink-500 border-b border-slate-100 dark:border-zink-500 last:border-0">
                        <div class="flex items-center justify-center w-9 h-9 rounded-full <?= $n['color'] ?> shrink-0">
                            <i data-lucide="<?= $n['icon'] ?>" class="w-4 h-4"></i>
                        </div>
                        <div class="grow min-w-0">
                            <p class="text-sm text-slate-700 dark:text-zink-100 leading-snug"><?= $n['title'] ?></p>
                            <p class="text-xs text-slate-400 dark:text-zink-300 mt-0.5"><?= e($n['sub']) ?></p>
                        </div>
                        <span class="text-[11px] text-slate-400 dark:text-zink-400 shrink-0 mt-0.5"><?= timeAgo($n['time']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                                <div class="flex items-center gap-2 p-4 border-t border-slate-200 dark:border-zink-500">
                                    <div class="grow">
                                        <a href="#!">Manage Notification</a>
                                    </div>
                                    <div class="shrink-0">
                                        <button type="button" class="px-2 py-1.5 text-xs text-white transition-all duration-200 ease-linear btn bg-custom-500 border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 focus:text-white focus:bg-custom-600 focus:border-custom-600 focus:ring focus:ring-custom-100 active:text-white active:bg-custom-600 active:border-custom-600 active:ring active:ring-custom-100">View All Notification <i data-lucide="move-right" class="inline-block w-3.5 h-3.5 ml-1"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
    
                        <div class="relative items-center hidden h-header md:flex">
                            <button data-drawer-target="customizerButton" type="button" class="inline-flex justify-center items-center p-0 text-topbar-item transition-all w-[37.5px] h-[37.5px] duration-200 ease-linear bg-topbar group-data-[topbar=dark]:text-topbar-item-dark rounded-md btn hover:bg-topbar-item-bg-hover hover:text-topbar-item-hover group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=dark]:dark:hover:text-zink-50 group-data-[topbar=dark]:dark:text-zink-200">
                                <i data-lucide="settings" class="inline-block w-5 h-5 stroke-1 fill-slate-100 group-data-[topbar=dark]:fill-topbar-item-bg-hover-dark group-data-[topbar=brand]:fill-topbar-item-bg-hover-brand"></i>
                            </button>
                        </div>
    
                        <div class="relative flex items-center dropdown h-header">
                            <button type="button" class="inline-block p-0 transition-all duration-200 ease-linear bg-topbar rounded-full text-topbar-item dropdown-toggle btn hover:bg-topbar-item-bg-hover hover:text-topbar-item-hover group-data-[topbar=dark]:text-topbar-item-dark group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=dark]:dark:hover:text-zink-50 group-data-[topbar=dark]:dark:text-zink-200" id="dropdownMenuButton" data-bs-toggle="dropdown">
                                <div class="bg-pink-100 rounded-full">
                                    <img src="../assets/images/logo.png" alt="" class="w-[37.5px] h-[37.5px] rounded-full">
                                </div>
                            </button>
                            <div class="absolute z-50 hidden p-4 ltr:text-left rtl:text-right bg-white rounded-md shadow-md !top-4 dropdown-menu min-w-[14rem] dark:bg-zink-600" aria-labelledby="dropdownMenuButton">
                                <h6 class="mb-2 text-sm font-normal text-slate-500 dark:text-zink-300">Welcome to starcode</h6>
                                <a href="#!" class="flex gap-3 mb-3">
                                    <div class="relative inline-block shrink-0">
                                        <div class="rounded bg-slate-100 dark:bg-zink-500">
                                            <img src="../assets/images/logo.png" alt="" class="w-12 h-12 rounded">
                                        </div>
                                        <span class="-top-1 ltr:-right-1 rtl:-left-1 absolute w-2.5 h-2.5 bg-green-400 border-2 border-white rounded-full dark:border-zink-600"></span>
                                    </div>
                                    <div>
                                        <h6 class="mb-1 text-15"><?= e($current_user['name']) ?></h6>
                                        <p class="text-slate-500 dark:text-zink-300"><?= e($current_user['role_name']) ?></p>
                                    </div>
                                </a>
                                <ul>
                                    <li>
                                        <a class="block ltr:pr-4 rtl:pl-4 py-1.5 text-base font-medium transition-all duration-200 ease-linear text-slate-600 dropdown-item hover:text-custom-500 focus:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:focus:text-custom-500" href="#"><i data-lucide="user-2" class="inline-block size-4 ltr:mr-2 rtl:ml-2"></i> Profile</a>
                                    </li>
                                    <?php if (isAdmin()): ?>
                                        <li>
                                            <a class="flex items-center gap-2 px-2 py-1.5 text-sm text-slate-600 dark:text-zink-200 rounded hover:bg-slate-50 dark:hover:bg-zink-500 hover:text-custom-500 dark:hover:text-custom-500 transition-colors" href="settings.php">
                                                <i data-lucide="settings" class="w-4 h-4"></i> Settings
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    <li class="pt-2 mt-2 border-t border-slate-200 dark:border-zink-500">
                                        <a class="block ltr:pr-4 rtl:pl-4 py-1.5 text-base font-medium transition-all duration-200 ease-linear text-slate-600 dropdown-item hover:text-custom-500 focus:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:focus:text-custom-500" href="../auth/logout.php"><i data-lucide="log-out" class="inline-block size-4 ltr:mr-2 rtl:ml-2"></i> Sign Out</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    