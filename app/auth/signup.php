<?php
session_start();
require_once '../config/db.php';

$error = "";
$success = false;

if (isset($_POST['signup'])) {

    // 1. INPUTS
    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    $company_name  = trim($_POST['company_name'] ?? '');
    $tin           = trim($_POST['tin'] ?? '');
    $business_type = $_POST['business_type'] ?? '';
    $location_name = trim($_POST['location'] ?? '');

    $lat = $_POST['latitude'] ?? null;
    $lon = $_POST['longitude'] ?? null;

    // 2. VALIDATION
    if (!$name || !$email || !$password || !$role) {
        $error = "All required fields must be filled.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!in_array($role, ['farmer','buyer','extension'])) {
        $error = "Invalid role selected.";
    } elseif ($role === 'buyer' && empty($company_name)) {
        $error = "Company name required for buyers.";
    }

    if (empty($error)) {

        // 3. CHECK DUPLICATE EMAIL
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Email already exists.";
        } else {
            $stmt->close();

            // 4. HASH PASSWORD
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // 5. INSERT USER
            $sql = "INSERT INTO users 
            (name, email, phone, role, password, location_name, company_name, tin, business_type, location_lat, location_lon, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $status = 'active';

            $stmt->bind_param(
                "ssssssssssss",
                $name,
                $email,
                $phone,
                $role,
                $hashed,
                $location_name,
                $company_name,
                $tin,
                $business_type,
                $lat,
                $lon,
                $status
            );

            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = "Signup failed. Try again.";
            }

            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <!-- Meta tags  -->
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">

    <title>TB - GxAlert Management System</title>
    <link rel="icon" type="image/png" href="../images/favicon.png">

    <!-- CSS Assets -->
    <link rel="stylesheet" href="../css/app.css">

    <!-- Javascript Assets -->
    <script src="../js/app.js" defer=""></script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="../css2?family=Inter:wght@400;500;600;700&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <script>
      /**
       * THIS SCRIPT REQUIRED FOR PREVENT FLICKERING IN SOME BROWSERS
       */
      localStorage.getItem("_x_darkMode_on") === "true" &&
        document.documentElement.classList.add("dark");
    </script>
  </head>
  <body x-data="" class="is-header-blur">
    
    <!-- Page Wrapper -->
    <div id="root" class="min-h-100vh flex grow bg-slate-50 dark:bg-navy-900" x-cloak="">
      
      <!-- LEFT SIDE (Carousel - Copy exact same from login) -->
      <div class="fixed top-0 hidden p-6 lg:block lg:px-12">
        <a href="#" class="flex items-center space-x-2">
          <img class="size-12" src="../images/app-logo.png" alt="logo">
          <p class="text-xl font-semibold uppercase text-slate-700 dark:text-navy-100">TB - GxAlert Management System</p>
        </a>
      </div>
      <div class="hidden w-full place-items-center lg:grid">
        <div class="w-full max-w-lg p-20">
          <!-- <img class="w-full" x-show="!$store.global.isDarkModeEnabled" src="../images/illustrations/dashboard-check.svg" alt="image">
          <img class="w-full" x-show="$store.global.isDarkModeEnabled" src="../images/illustrations/dashboard-check-dark.svg" alt="image"> -->
           <div
                x-init="$nextTick(()=>$el._x_swiper = new Swiper($el, {scrollbar: {el: '.swiper-scrollbar',draggable: true}, navigation: {prevEl: '.swiper-button-prev',nextEl: '.swiper-button-next'},autoplay: {delay: 3000}}))"
                class="swiper rounded-lg"
              >
                <div class="swiper-wrapper">
                  <div class="swiper-slide">
                    <img
                      class="h-full w-full object-cover"
                      src="bg1.jpg"
                      alt=""
                    />
                  </div>
                  <div class="swiper-slide">
                    <img
                      class="h-full w-full object-cover object-top"
                      src="bg3.jpg"
                      alt=""
                    />
                  </div>
                  <div class="swiper-slide">
                    <img
                      class="h-full w-full object-cover object-top"
                      src="bg2.jpg"
                      alt=""
                    />
                  </div>
                  <div class="swiper-slide">
                    <img
                      class="h-full w-full object-cover object-top"
                      src="bg6.jpg"
                      alt=""
                    />
                  </div>
                  <div class="swiper-slide">
                    <img
                      class="h-full w-full object-cover object-center"
                      src="bg7.jpg"
                      alt=""
                    />
                  </div>
                </div>
                <div class="swiper-scrollbar"></div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
              </div>
        </div>
      </div>

      <!-- RIGHT SIDE (Form) -->
      <!-- x-data handles showing/hiding buyer fields -->
      <main class="flex w-full flex-col items-center bg-white dark:bg-navy-700 lg:max-w-md lg:overflow-y-auto">
        <div class="flex w-full max-w-sm grow flex-col justify-center p-5">
          
          <div class="text-center">
            <img class="mx-auto size-16 lg:hidden" src="../images/app-logo.png" alt="logo">
            <div class="mt-4">
              <h2 class="text-2xl font-semibold text-slate-600 dark:text-navy-100">Create Account</h2>
              <p class="text-slate-400 dark:text-navy-300">Join the agricultural network</p>
            </div>
          </div>

          <!-- ERROR MESSAGE -->
          <?php if(!empty($error)): ?>
             <div
              class=" mt-6 mb-2 alert flex overflow-hidden rounded-lg border border-info text-info"
            >
              <div class="bg-info p-3 text-white">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="size-5"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
              </div>
              <div class="px-4 py-3 sm:px-5"><?php echo htmlspecialchars($error); ?></div>
            </div>
          <?php endif; ?>

          <!-- SUCCESS MESSAGE -->
          <?php if($success): ?>
             <div class="mt-6 mb-2 alert flex overflow-hidden rounded-lg bg-success/10 text-success dark:bg-success/15">
                <div class="flex flex-1 items-center space-x-3 p-4">
                  <div class="flex-1 text-sm">Account created successfully! Redirecting to login...</div>
                </div>
                <div class="w-1.5 bg-success"></div>
             </div>
             <script>
                // Redirect to login after 2 seconds
                setTimeout(function(){ window.location.href = 'index.php?status=registered'; }, 2000);
             </script>
          <?php endif; ?>

          <form method="POST" action="" x-data="{ role: '<?php echo $_POST['role'] ?? ''; ?>' }">
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">
            <div class="mt-8 space-y-4">
              
              <!-- Name -->
              <label class="relative flex">
                <span class="sr-only">Full Name</span>
                <input class="form-input peer w-full rounded-lg bg-slate-150 px-3 py-2 pl-9 ring-primary/50 placeholder:text-slate-400 hover:bg-slate-200 focus:ring dark:bg-navy-900/90 dark:ring-accent/50 dark:placeholder:text-navy-300 dark:hover:bg-navy-900 dark:focus:bg-navy-900" placeholder="Full Name" type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                <span class="pointer-events-none absolute flex h-full w-10 items-center justify-center text-slate-400 peer-focus:text-primary dark:text-navy-300 dark:peer-focus:text-accent">
                   <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                </span>
              </label>

              <!-- Email -->
              <label class="relative flex">
                <span class="sr-only">Email Address</span>
                <input class="form-input peer w-full rounded-lg bg-slate-150 px-3 py-2 pl-9 ring-primary/50 placeholder:text-slate-400 hover:bg-slate-200 focus:ring dark:bg-navy-900/90 dark:ring-accent/50 dark:placeholder:text-navy-300 dark:hover:bg-navy-900 dark:focus:bg-navy-900" placeholder="Email Address" type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                <span class="pointer-events-none absolute flex h-full w-10 items-center justify-center text-slate-400 peer-focus:text-primary dark:text-navy-300 dark:peer-focus:text-accent">
                   <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                </span>
              </label>

              <!-- Phone -->
              <label class="relative flex">
                <span class="sr-only">Phone Number</span>
                <input class="form-input peer w-full rounded-lg bg-slate-150 px-3 py-2 pl-9 ring-primary/50 placeholder:text-slate-400 hover:bg-slate-200 focus:ring dark:bg-navy-900/90 dark:ring-accent/50 dark:placeholder:text-navy-300 dark:hover:bg-navy-900 dark:focus:bg-navy-900" placeholder="Phone Number (e.g., 077xxxxxxx)" type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                <span class="pointer-events-none absolute flex h-full w-10 items-center justify-center text-slate-400 peer-focus:text-primary dark:text-navy-300 dark:peer-focus:text-accent">
                   <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                </span>
              </label>

              <!-- ROLE SELECT (Crucial Part) -->
              <div>
                 <select name="role" @change="role = $event.target.value" required class="form-select w-full rounded-lg bg-slate-150 px-3 py-2 ring-primary/50 text-slate-600 hover:bg-slate-200 focus:ring dark:bg-navy-900/90 dark:ring-accent/50 dark:text-navy-100 dark:hover:bg-navy-900 dark:focus:bg-navy-900">
                    <option value="" disabled selected>Select your role</option>
                    <option value="farmer" <?php echo (($_POST['role'] ?? '') == 'farmer') ? 'selected' : ''; ?>>Farmer</option>
                    <option value="buyer" <?php echo (($_POST['role'] ?? '') == 'buyer') ? 'selected' : ''; ?>>Buyer / Wholesaler</option>
                    <option value="extension" <?php echo (($_POST['role'] ?? '') == 'extension') ? 'selected' : ''; ?>>Extension Worker</option>
                 </select>
              </div>

              <!-- CONDITIONAL BUYER FIELDS (Magic happens here) -->
              <div x-show="role === 'buyer'" x-transition.duration.300ms class="space-y-4">
                 <input class="form-input w-full rounded-lg bg-slate-150 px-3 py-2 ring-primary/50 placeholder:text-slate-400 hover:bg-slate-200 focus:ring dark:bg-navy-900/90 dark:ring-accent/50 dark:placeholder:text-navy-300 dark:hover:bg-navy-900 dark:focus:bg-navy-900" placeholder="Company / Business Name" type="text" name="company_name" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                 
                 <select name="business_type" class="form-select w-full rounded-lg bg-slate-150 px-3 py-2 ring-primary/50 text-slate-600 hover:bg-slate-200 focus:ring dark:bg-navy-900/90 dark:ring-accent/50 dark:text-navy-100 dark:hover:bg-navy-900 dark:focus:bg-navy-900">
                    <option value="" disabled selected>Business Type</option>
                    <option value="wholesaler">Wholesaler</option>
                    <option value="processor">Processor</option>
                    <option value="exporter">Exporter</option>
                    <option value="retailer">Retailer</option>
                    <option value="cooperative">Cooperative</option>
                    <option value="other">Other</option>
                 </select>

                 <input class="form-input w-full rounded-lg bg-slate-150 px-3 py-2 ring-primary/50 placeholder:text-slate-400 hover:bg-slate-200 focus:ring dark:bg-navy-900/90 dark:ring-accent/50 dark:placeholder:text-navy-300 dark:hover:bg-navy-900 dark:focus:bg-navy-900" placeholder="TIN (Tax Identification Number)" type="text" name="tin" value="<?php echo htmlspecialchars($_POST['tin'] ?? ''); ?>">
              </div>

              <!-- Location -->
              <label class="relative flex">
                <span class="sr-only">District / Location</span>
                <input class="form-input peer w-full rounded-lg bg-slate-150 px-3 py-2 pl-9 ring-primary/50 placeholder:text-slate-400 hover:bg-slate-200 focus:ring dark:bg-navy-900/90 dark:ring-accent/50 dark:placeholder:text-navy-300 dark:hover:bg-navy-900 dark:focus:bg-navy-900" placeholder="District / Location" type="text" name="location" value="<?php echo htmlspecialchars($_POST['location'] ?? 'Kampala, Uganda'); ?>">
                <span class="pointer-events-none absolute flex h-full w-10 items-center justify-center text-slate-400 peer-focus:text-primary dark:text-navy-300 dark:peer-focus:text-accent">
                   <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </span>
              </label>

              <!-- Password -->
              <label class="relative flex">
                <span class="sr-only">Password</span>
                <input class="form-input peer w-full rounded-lg bg-slate-150 px-3 py-2 pl-9 pr-10 ring-primary/50 placeholder:text-slate-400 hover:bg-slate-200 focus:ring dark:bg-navy-900/90 dark:ring-accent/50 dark:placeholder:text-navy-300 dark:hover:bg-navy-900 dark:focus:bg-navy-900" placeholder="Create Password (min 6 characters)" type="password" name="password" required>
                <span class="pointer-events-none absolute flex h-full w-10 items-center justify-center text-slate-400 peer-focus:text-primary dark:text-navy-300 dark:peer-focus:text-accent">
                   <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                </span>
              </label>

            </div>

            <button name="signup" type="submit" class="btn mt-10 h-10 w-full bg-primary font-medium text-white hover:bg-primary-focus focus:bg-primary-focus active:bg-primary-focus/90 dark:bg-accent dark:hover:bg-accent-focus dark:focus:bg-accent-focus dark:active:bg-accent/90">
              Create Account
            </button>
          </form>

          <div class="mt-4 text-center text-xs+">
            <p class="line-clamp-1">
              <span>Already have an account?</span>
              <a class="text-primary transition-colors hover:text-primary-focus dark:text-accent-light dark:hover:text-accent" href="index.php">Sign In</a>
            </p>
          </div>
          
          <div class="my-7 flex items-center space-x-3">
            <div class="h-px flex-1 bg-slate-200 dark:bg-navy-500"></div>
            <p>~</p>
            <div class="h-px flex-1 bg-slate-200 dark:bg-navy-500"></div>
          </div>
        </div>
        
        <div class="my-5 flex justify-center text-xs text-slate-400 dark:text-navy-300">
          <a href="#">Privacy Notice</a>
          <div class="mx-3 my-1 w-px bg-slate-200 dark:bg-navy-500"></div>
          <a href="#">Term of service</a>
        </div>
      </main>
    </div>

    <div id="x-teleport-target"></div>
    <script>window.addEventListener("DOMContentLoaded", () => Alpine.start());</script>
    <script>
      document.addEventListener("DOMContentLoaded", function () {
          if (navigator.geolocation) {
              navigator.geolocation.getCurrentPosition(
                  function (position) {
                      document.getElementById("latitude").value = position.coords.latitude;
                      document.getElementById("longitude").value = position.coords.longitude;
                  },
                  function (error) {
                      console.log("Location permission denied or unavailable.");
                  },
                  { enableHighAccuracy: true, timeout: 5000 }
              );
          }
      });
      </script>
  </body>
</html>