  <!--  F O O T E R  -->
  <footer>
    <div class="container">
      <div class="row">
        <div class="col-md-3">
          <h5>Menu</h5>
          <ul>
              <?php
              foreach ($pages as $filename => $page) {
                  // skip hidden menu items
                  if (empty($page['menu']) || $page['menu'] !== 'main') continue;
                  // check auth
                  if ($page['auth_level'] > $userAuthLevel) continue;

                  // highlight active page
                  $activeClass = ($filename === $currentPage) ? ' active' : '';

                  echo '<li class="' . $activeClass . '">';
                  echo '<a href="/' . htmlspecialchars($filename) . '">' . htmlspecialchars($page['title']) . '</a>';
                  echo '</li>' . PHP_EOL;
              }
              ?>
          </ul>
        </div>
        <div class="col-md-3">
          <h5>Admin Tools</h5>
          <ul>
              <?php
              foreach ($pages as $filename => $page) {
                  // skip hidden menu items
                  if (empty($page['menu']) || $page['menu'] !== 'admin') continue;
                  // check auth
                  if ($page['auth_level'] > $userAuthLevel) continue;

                  // highlight active page
                  $activeClass = ($filename === $currentPage) ? ' active' : '';

                  echo '<li class="' . $activeClass . '">';
                  echo '<a href="/' . htmlspecialchars($filename) . '">' . htmlspecialchars($page['title']) . '</a>';
                  echo '</li>' . PHP_EOL;
              }
              ?>
          </ul>
        </div>
        <div class="col-md-3">
          <h5>Planning</h5>
          <ul>
              <?php
              foreach ($pages as $filename => $page) {
                  // skip hidden menu items
                  if (empty($page['menu']) || $page['menu'] !== 'plan') continue;
                  // check auth
                  if ($page['auth_level'] > $userAuthLevel) continue;

                  // highlight active page
                  $activeClass = ($filename === $currentPage) ? ' active' : '';

                  echo '<li class="' . $activeClass . '">';
                  echo '<a href="/' . htmlspecialchars($filename) . '">' . htmlspecialchars($page['title']) . '</a>';
                  echo '</li>' . PHP_EOL;
              }
              ?>
          </ul>
        </div>
          <div class="col-md-3">
              <h5>Promato</h5>
              <p>Say hello to Promato! your ultimate sidekick! From project dashboards to personal Kanban board, it keeps tabs on tasks, tracks capacity, and makes sure nothing slips through the cracks. Work smarter, not harder—and have a little fun while you're at it!</p>
          </div>
      </div>
  </footer>
  <!--  E N D  F O O T E R  -->
    

    <!-- External JavaScripts -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js" integrity="sha384-smHYKdLADwkXOn1EmN1qk/HfnUcbVRZyYmZ4qpPea6sjB/pTJ0euyQp0Mk8ck+5T" crossorigin="anonymous"></script>
  </body>
</html>
