<?php
session_start();
$insert = false;
$update = false;
$delete = false;

$servername ="localhost";
$username ="root";
$password ="";
$database="notes";

$conn = mysqli_connect($servername,$username,$password,$database);
if (!$conn){
  die("sorry we failed to connect: ".mysqli_connect_error());
}

// ------------------- HELPER: flash alerts -------------------
function flash($type, $msg) {
  $_SESSION['flash'][] = ['type'=>$type, 'msg'=>$msg];
}
if (!isset($_SESSION['flash'])) $_SESSION['flash'] = [];

// ------------------- AUTH HANDLERS (REGISTER/LOGIN/LOGOUT) -------------------
if ($_SERVER['REQUEST_METHOD']==='POST') {
  // REGISTER
  if (isset($_POST['action']) && $_POST['action']==='register') {
    $user = trim($_POST['reg_username'] ?? '');
    $pass = $_POST['reg_password'] ?? '';
    if ($user==='' || $pass==='') {
      flash('danger','Username and password are required.');
    } else {
      // Check if exists
      $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username=?");
      mysqli_stmt_bind_param($stmt, "s", $user);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_store_result($stmt);
      if (mysqli_stmt_num_rows($stmt) > 0) {
        flash('warning','Username already taken.');
      } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt2 = mysqli_prepare($conn, "INSERT INTO users (username, password) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt2, "ss", $user, $hash);
        if (mysqli_stmt_execute($stmt2)) {
          flash('success','Registration successful! Please log in.');
        } else {
          flash('danger','Registration failed: '.htmlspecialchars(mysqli_error($conn)));
        }
        mysqli_stmt_close($stmt2);
      }
      mysqli_stmt_close($stmt);
    }
  }

  // LOGIN
  if (isset($_POST['action']) && $_POST['action']==='login') {
    $user = trim($_POST['log_username'] ?? '');
    $pass = $_POST['log_password'] ?? '';
    $stmt = mysqli_prepare($conn, "SELECT id, password FROM users WHERE username=?");
    mysqli_stmt_bind_param($stmt, "s", $user);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
      if (password_verify($pass, $row['password'])) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $user;
        $_SESSION['user_id'] = (int)$row['id'];
        flash('success','Logged in successfully.');
        // Redirect to clear POST
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
      }
    }
    flash('danger','Invalid username or password.');
    mysqli_stmt_close($stmt);
  }

  // LOGOUT
  if (isset($_POST['action']) && $_POST['action']==='logout') {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash'] = [['type'=>'success','msg'=>'Logged out successfully.']];
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
  }
}

// ------------------- PROTECT CRUD: require login -------------------
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin']===true;

// ------------------- CRUD HANDLERS (only if logged in) -------------------
if ($loggedIn) {
  // DELETE via GET ?delete=ID (you already had this)
  if (isset($_GET['delete'])) {
    $sno = (int)$_GET['delete'];
    $stmt = mysqli_prepare($conn,"DELETE FROM notes WHERE sno=?");
    mysqli_stmt_bind_param($stmt,"i",$sno);
    if (mysqli_stmt_execute($stmt)) {
      $delete = true;
      flash('success','Your note has been deleted successfully.');
    } else {
      flash('danger','Delete failed: '.htmlspecialchars(mysqli_error($conn)));
    }
    mysqli_stmt_close($stmt);
  }

  if ($_SERVER['REQUEST_METHOD']==='POST') {
    // UPDATE
    if (isset($_POST['snoEdit'])) {
      $sno = (int)$_POST['snoEdit'];
      $title = $_POST['titleEdit'] ?? '';
      $description = $_POST['descriptionEdit'] ?? '';
      $stmt = mysqli_prepare($conn,"UPDATE notes SET title=?, description=? WHERE sno=?");
      mysqli_stmt_bind_param($stmt,"ssi",$title,$description,$sno);
      if (mysqli_stmt_execute($stmt)) {
        $update = true;
        flash('success','Your note has been updated successfully.');
      } else {
        flash('danger','Update failed: '.htmlspecialchars(mysqli_error($conn)));
      }
      mysqli_stmt_close($stmt);
    }
    // INSERT
    if (isset($_POST['title']) && isset($_POST['description']) && !isset($_POST['snoEdit'])) {
      $title = $_POST['title'];
      $description = $_POST['description'];
      $stmt = mysqli_prepare($conn,"INSERT INTO notes (title, description) VALUES (?, ?)");
      mysqli_stmt_bind_param($stmt,"ss",$title,$description);
      if (mysqli_stmt_execute($stmt)) {
        $insert = true;
        flash('success','Your note has been inserted successfully.');
      } else {
        flash('danger','Insert failed: '.htmlspecialchars(mysqli_error($conn)));
      }
      mysqli_stmt_close($stmt);
    }
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>project 1-php crud</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link  rel="stylesheet" href="crud.css">
    <link rel="stylesheet" href="//cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">

    <!-- JS libs -->
    <script src="https://code.jquery.com/jquery-3.7.1.js" crossorigin="anonymous"></script>
    <script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
  </head>
  <body>
    <!-- Edit Modal -->
    <div class="modal fade" id="editmodal" tabindex="-1" aria-labelledby="editmodal" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h1 class="modal-title fs-5" id="editmodalLabel">Edit this Note</h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <?php if ($loggedIn): ?>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post">
              <input type="hidden" name="snoEdit" id="snoEdit">
              <div class="mb-3">
                <label for="titleEdit" class="form-label">Note title</label>
                <input type="text" class="form-control" id="titleEdit" name="titleEdit" required>
              </div>
              <div class="mb-3">
                <label for="descriptionEdit">Note description</label>
                <textarea class="form-control" id="descriptionEdit" name="descriptionEdit" style="height: 120px" required></textarea>
              </div>
              <button type="submit" class="btn btn-primary">Update Note</button>
            </form>
            <?php else: ?>
              <div class="alert alert-warning mb-0">Please log in to edit notes.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
          <div class="modal-header">
            <h5 class="modal-title">Log in</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
              <input type="hidden" name="action" value="login">
              <div class="mb-3">
                <label class="form-label">Username</label>
                <input class="form-control" name="log_username" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="log_password" required>
              </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-primary" type="submit">Log in</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Register Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
          <div class="modal-header">
            <h5 class="modal-title">Register</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
              <input type="hidden" name="action" value="register">
              <div class="mb-3">
                <label class="form-label">Username</label>
                <input class="form-control" name="reg_username" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="reg_password" required>
              </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-success" type="submit">Create account</button>
          </div>
        </form>
      </div>
    </div>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
      <div class="container-fluid">
        <a class="navbar-brand" href="#">Navbar</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Link</a></li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">Dropdown</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#">Action</a></li>
                <li><a class="dropdown-item" href="#">Another action</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#">Something else here</a></li>
              </ul>
            </li>
            <li class="nav-item"><a class="nav-link disabled" href="#" tabindex="-1">Disabled</a></li>
          </ul>

          <!-- Right side auth -->
          <div class="d-flex">
            <?php if ($loggedIn): ?>
              <span class="navbar-text text-white me-3">Hi, <?= htmlspecialchars($_SESSION['username']) ?></span>
              <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-outline-light btn-sm">Logout</button>
              </form>
            <?php else: ?>
              <button class="btn btn-outline-light btn-sm me-2" data-bs-toggle="modal" data-bs-target="#loginModal">Login</button>
              <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#registerModal">Register</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </nav>

    <!-- FLASH ALERTS -->
    <div class="container mt-3">
      <?php foreach ($_SESSION['flash'] as $f): ?>
        <div class="alert alert-<?= $f['type'] ?> alert-dismissible fade show" role="alert">
          <strong><?= ucfirst($f['type']) ?>:</strong> <?= htmlspecialchars($f['msg']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; $_SESSION['flash']=[]; ?>
    </div>

    <!-- ADD NOTE (only if logged in) -->
    <div class="container my-4">
      <h2>Add a Note</h2>
      <?php if ($loggedIn): ?>
      <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post">
        <div class="mb-3">
          <label for="title" class="form-label">Note title</label>
          <input type="text" class="form-control" id="title" name="title" required>
        </div>
        <div class="mb-3">
          <label for="description">Note description</label>
          <textarea class="form-control" id="description" name="description" style="height: 100px" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Add Note</button>
      </form>
      <?php else: ?>
        <div class="alert alert-warning">Please log in to add notes.</div>
      <?php endif; ?>
    </div>

    <!-- TABLE -->
    <div class="container my-4">
      <table class="table" id="myTable">
        <thead>
          <tr>
            <th scope="col">S.no</th>
            <th scope="col">title</th>
            <th scope="col">description</th>
            <th scope="col">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php 
          $sql ="SELECT * FROM notes";
          $result = mysqli_query($conn, $sql);
          $sno=0;
          while ($row = mysqli_fetch_assoc($result)) {
            $sno++;
            echo "<tr>
              <th scope='row'>".$sno."</th>
              <td>".htmlspecialchars($row['title'])."</td>
              <td>".htmlspecialchars($row['description'])."</td>
              <td>";
            if ($loggedIn) {
              echo "<button class='edit btn btn-sm btn-primary' id='".$row['sno']."'>Edit</button>
                    <button class='delete btn btn-sm btn-danger' id='d".$row['sno']."'>Delete</button>";
            } else {
              echo "<span class='text-muted'>Login to manage</span>";
            }
            echo "</td></tr>";
          }
        ?>
        </tbody>
      </table>
    </div>

    <hr>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      let table = new DataTable('#myTable');
    </script>
    <script>
      // Edit handlers
        document.addEventListener("DOMContentLoaded", () => {
        const edits = document.getElementsByClassName("edit");
        Array.from(edits).forEach((element) => {
          element.addEventListener("click", function(e) {
            const tr = e.target.closest("tr");
            const title = tr.getElementsByTagName("td")[0].innerText;
            const description = tr.getElementsByTagName("td")[1].innerText;

            document.getElementById('titleEdit').value = title;
            document.getElementById('descriptionEdit').value = description;
            document.getElementById('snoEdit').value = e.target.id;

            var myModal = new bootstrap.Modal(document.getElementById('editmodal'));
            myModal.show();
          });
          });
        });
    </script>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const deletes = document.getElementsByClassName("delete");
        Array.from(deletes).forEach((element) => {
          element.addEventListener("click", function(e) {
            const sno = e.target.id.substring(1);
            if (confirm("Delete this note?")) {
              window.location = <?= json_encode($_SERVER['PHP_SELF']) ?> + "?delete=" + sno;
            }
          });
        });
      });
    </script>
  </body>
</html>