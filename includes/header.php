<?php
require_once 'auth.php';

// Check if a new year is being selected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newYear'])) {
    $newYear = $_POST['newYear'];
    $_SESSION['selectedYear'] = (int)$_POST['newYear'];
    echo("newYear = $newYear - SESSION=" . $_SESSION['selectedYear']);
}

// Default to 0, which indicates no specific year selected
$selectedYear = 0;

// Check if a year is set in the session
if (isset($_SESSION['selectedYear']) && is_numeric($_SESSION['selectedYear'])) {
    $selectedYear = (int)$_SESSION['selectedYear'];
    echo("selectedYear = $selectedYear - SESSION=" . $_SESSION['selectedYear']);
}

// If no year is selected, use the current year
if ($selectedYear === 0) {
    $selectedYear = (int)date("Y");
}

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
  <link rel="stylesheet" href="style/dropdown.css" type="text/css" />

  <!-- Ionic icons -->
  <link href="https://unpkg.com/ionicons@4.2.0/dist/css/ionicons.min.css" rel="stylesheet">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700,900" rel="stylesheet">

  <link rel="icon" type="image/x-icon" href="/images/favicon.ico">

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
  
  <script>
  function toggleYearDropdown() {
    document.getElementById("yearDropdown").style.display = 
      document.getElementById("yearDropdown").style.display === "none" ? "block" : "none";
  }
  
  function selectYear(year) {
    // Set the value in the hidden form field
    document.getElementById("newYear").value = year;
    // Submit the form to reload the page with the new year
    document.getElementById("yearSelectForm").submit();
  }
  
  // Close the dropdown if clicked outside
  window.onclick = function(event) {
    if (!event.target.matches('.dropdown-toggle')) {
      var dropdowns = document.getElementsByClassName("dropdown-menu");
      for (var i = 0; i < dropdowns.length; i++) {
        var openDropdown = dropdowns[i];
        if (openDropdown.style.display === "block") {
          openDropdown.style.display = "none";
        }
      }
    }
  }
</script>

</head>

<body>
        <!-- Hidden form for POST submission -->
        <form id="yearSelectForm" method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
          <input type="hidden" id="newYear" name="newYear" value="">
        </form>

  <!-- N A V B A R -->
  <nav class="navbar navbar-default navbar-expand-lg fixed-top custom-navbar">
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavDropdown"
      aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
      <span class="icon ion-md-menu"></span>
    </button>
      <img src="images/logo.png" class="img-fluid nav-logo-mobile" alt="Promato" style="height:20px;">
      <div class="collapse navbar-collapse" id="navbarNavDropdown">
      <div class="container">
        <a href="/"><img src="images/logo.png" class="img-fluid nav-logo-desktop" alt="Promato" style="height:40px;"></a>  
        <div class="nav-item nav-custom-link btn btn-demo-small dropdown">
          <button onclick="toggleYearDropdown()" class="dropdown-toggle">
            <?= $selectedYear ?> <span class="caret"></span>
          </button>
          <div id="yearDropdown" class="dropdown-menu" style="display: none;">
            <div onclick="selectYear(2024)" class="year-option">2024</div>
            <div onclick="selectYear(2025)" class="year-option">2025</div>
            <div onclick="selectYear(2026)" class="year-option">2026</div>
          </div>
        </div>

        <ul class="navbar-nav ml-auto nav-right" data-easing="easeInOutExpo" data-speed="1250" data-offset="65">

            <?php
            foreach ($pages as $filename => $page) {
                // skip hidden menu items
                if (empty($page['inhead']) || !$page['inhead']) continue;
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
                    <a class="nav-link" href="/dashboard.php"><?= htmlspecialchars($_SESSION['user_name']) ?><i class="icon ion-ios-arrow-forward icon-mobile"></i></a>
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
