<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
<title>TB - Password Reset</title>

<link rel="icon" type="image/png" href="../images/favicon.png">

<link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;0,700;0,900;1,400;1,500;1,700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="assets/plugins/bootstrap/css/bootstrap.min.css">

<link rel="stylesheet" href="assets/plugins/feather/feather.css">

<link rel="stylesheet" href="assets/plugins/icons/flags/flags.css">

<link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
<link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">

<link rel="stylesheet" href="assets/css/style.css">
<style>
    .main-wrapper.login-body {
        min-height: 100vh;
        background: linear-gradient(rgba(0, 0, 0, 0.40), rgba(0, 0, 0, 0.45)),
                    url('bg2.jpg') center/cover no-repeat fixed;
        background-attachment: fixed;
    }

    .login-left {
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(16px) saturate(180%);
        -webkit-backdrop-filter: blur(16px) saturate(180%);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 1.5rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
        position: relative;
        overflow: hidden;
    }

    .login-left img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0.9;
        z-index: -1;
    }
    .login-right-wrap
    {
 /* display: flex;
  justify-content: center;
  align-items: center;
  height: 300px;
  width: 500px;
  margin: 0px auto;*/
  background-color: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(5px);
  border-radius: 1rem;
    }
</style>
</head>
<body>

<div class="main-wrapper login-body">
<div class="login-wrapper">
<div class="container">
<div class="loginbox">
<div class="login-left">
<img class="img-fluid" src="bg4.jpg" alt="Logo">
</div>
<div class="login-right">
<div class="login-right-wrap">
<h1>Reset Password</h1>
<p class="account-subtitle">Let Us Help You</p>

<form action="reset.php">
<div class="form-group">
<label>Enter your registered email address <span class="login-danger">*</span></label>
<input class="form-control" type="text">
<span class="profile-views"><i class="fas fa-envelope"></i></span>
</div>
<div class="form-group">
<button class="btn btn-primary btn-block" type="submit">Reset My Password</button>
</div>
</form>
<div class="form-group mb-0">
<a href="index.php" class="btn btn-primary primary-reset btn-block">Login</a>
</div>


</div>
</div>
</div>
</div>
</div>
</div>


<script src="assets/js/jquery-3.6.0.min.js"></script>

<script src="assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/feather.min.js"></script>

<script src="assets/js/script.js"></script>
</body>
</html>