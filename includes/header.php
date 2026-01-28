<?php
require_once 'auth.php';
require_once 'csrf.php';

function number_form($number, $decimals=-1) {
  $number = $number ?? 0;
  if ($decimals == -1){
    $decimals = fmod($number, 1)? 1 : 0;
  }
  return number_format($number,$decimals, ',','.');
}

// Check if a new year is being selected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newYear'])) {
    csrf_protect(); // Verify CSRF token
    $newYear = $_POST['newYear'];
    $_SESSION['selectedYear'] = (int)$_POST['newYear'];
}

// Default to 0, which indicates no specific year selected
$selectedYear = 0;

// Check if a year is set in the session
if (isset($_SESSION['selectedYear']) && is_numeric($_SESSION['selectedYear'])) {
    $selectedYear = (int)$_SESSION['selectedYear'];
}

// If no year is selected, use the current year
if ($selectedYear === 0) {
    $selectedYear = (int)date("Y");
}

// Auto-sync check (runs in background if scheduled)
require_once __DIR__ . '/auto_sync.php';

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

  <title><?= htmlspecialchars($pageInfo['Name'] ?? 'UNKNOWN PAGE') ?></title>
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

  // CSRF Token for AJAX requests
  window.csrfToken = '<?= csrf_token() ?>';

  // Add CSRF token to all fetch requests automatically
  const originalFetch = window.fetch;
  window.fetch = function(url, options = {}) {
    options.headers = options.headers || {};
    if (typeof options.headers === 'object' && !(options.headers instanceof Headers)) {
      options.headers['X-CSRF-Token'] = window.csrfToken;
    }
    return originalFetch(url, options);
  };
</script>

</head>

<body>
        <!-- Hidden form for POST submission -->
        <form id="yearSelectForm" method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
          <input type="hidden" id="newYear" name="newYear" value="">
          <?php csrf_field(); ?>
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
            <div onclick="selectYear(2027)" class="year-option">2027</div>
          </div>
        </div>

        <ul class="navbar-nav ml-auto nav-right" data-easing="easeInOutExpo" data-speed="1250" data-offset="65">

            <?php
            // Fetch header menu pages from database
            $headerMenusStmt = $pdo->prepare("
            SELECT 
                p.Path,
                p.Name
            FROM Pages p 
            LEFT JOIN PageAccess pa ON pa.PageId = p.Id AND pa.UserId = :userId
            WHERE p.Id IS NOT NULL AND p.InHead=1
                AND (p.Auth <= :authLevel OR pa.UserId IS NOT NULL)
            ORDER BY p.Id
            ");

            $headerMenusStmt->execute([
            ':userId' => $userId ?? 0,
            ':authLevel' => $userAuthLevel ?? 1
            ]);
            $headerResults = $headerMenusStmt->fetchAll(PDO::FETCH_ASSOC);
            $headerMenus = [];

            foreach ($headerResults as $page) {
                // highlight active page
                $activeClass = ($page['Path'] === $currentPage) ? ' active' : '';

                echo '<li class="nav-item nav-custom-link' . $activeClass . '">';
                echo '<a class="nav-link" href="/' . htmlspecialchars($page['Path']) . '">' . htmlspecialchars($page['Name']) . '<i class="icon ion-ios-arrow-forward icon-mobile"></i></a>';
                echo '</li>';
            }
            ?>

            <!-- Search Bar -->
            <li class="nav-item nav-search-container">
              <form action="/search.php" method="GET" class="nav-search-form">
                <input
                  type="text"
                  name="q"
                  id="navSearchInput"
                  class="nav-search-input"
                  placeholder="Search..."
                  autocomplete="off">
                <button type="submit" class="nav-search-btn">
                  <i class="icon ion-md-search"></i>
                </button>
              </form>
            </li>

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
