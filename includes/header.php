<?php
require_once 'auth.php';
?>

<!doctype html>
<html lang="en-US">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css"
    integrity="sha384-WskhaSGFgHYWDcbwN70/dfYBj47jz9qbsMId/iRN3ewGhXQFZCSftd1LZCfmhktB" crossorigin="anonymous">

  <!-- Custom Css -->
  <link rel="stylesheet" href="style/style.css" type="text/css" />

  <!-- Ionic icons -->
  <link href="https://unpkg.com/ionicons@4.2.0/dist/css/ionicons.min.css" rel="stylesheet">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700,900" rel="stylesheet">

  <link rel="icon" href="style/favicon.ico" type="image/x-icon" />

  <?php
    // Include any page-specific stylesheets
    if (!empty($pageSpecificCSS) && is_array($pageSpecificCSS)) {
        foreach ($pageSpecificCSS as $cssFile) {
            echo '<link rel="stylesheet" href="style/' . htmlspecialchars($cssFile) . '">' . PHP_EOL;
        }
    }
  ?>

  <title><?= $pages[$currentPage]['title'] ?></title>

  <script src="https://code.jquery.com/jquery-3.7.0.js"></script>

</head>

<body>

  <!-- N A V B A R -->
  <nav class="navbar navbar-default navbar-expand-lg fixed-top custom-navbar">
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavDropdown"
      aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
      <span class="icon ion-md-menu"></span>
    </button>
    <img src="images/logo.png" class="img-fluid nav-logo-mobile" alt="Company Logo">
    <div class="collapse navbar-collapse" id="navbarNavDropdown">
      <div class="container">
        <img src="images/logo.png" class="img-fluid nav-logo-desktop" alt="Company Logo">
        <ul class="navbar-nav ml-auto nav-right" data-easing="easeInOutExpo" data-speed="1250" data-offset="65">

            <?php
            foreach ($pages as $filename => $page) {
                // skip hidden menu items
                if (empty($page['menu']) || $page['menu'] !== 'main') continue;
                // check auth
                if ($page['auth_level'] > $userAuthLevel) continue;

                // highlight active page
                $activeClass = ($filename === $currentPage) ? ' active' : '';

                echo '<li class="nav-item nav-custom-link' . $activeClass . '">';
                echo '<a class="nav-link" href="/' . htmlspecialchars($filename) . '">' . htmlspecialchars($page['title']) . '<i class="icon ion-ios-arrow-forward icon-mobile"></i></a>';
                echo '</li>';
            }
            ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item nav-custom-link btn btn-demo-small">
                    <a class="nav-link" href="/"><?= htmlspecialchars($_SESSION['user_name']) ?><i class="icon ion-ios-arrow-forward icon-mobile"></i></a>
                </li>
            <?php else: ?>
                <li class="nav-item nav-custom-link btn btn-demo-small">
                    <a class="nav-link" href="/login.php">Login<i class="icon ion-ios-arrow-forward icon-mobile"></i></a>
                </li>
            <?php endif; ?>

        </ul>
      </div>
    </div>
  </nav>
  <!-- E N D  N A V B A R -->
