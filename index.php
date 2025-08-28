<?php
session_start();

// DB Connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "notes";
$conn = mysqli_connect($servername, $username, $password, $database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Flash messages
function flash($type, $msg) {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}
function display_flash() {
    if (isset($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $f) {
            echo "<div class='alert alert-{$f['type']} alert-dismissible fade show' role='alert'>
                    {$f['msg']}
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                  </div>";
        }
        unset($_SESSION['flash']);
    }
}

// Handle Create
if (isset($_POST['action']) && $_POST['action'] === "create") {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $sql = "INSERT INTO notes (title, description) VALUES ('$title','$desc')";
    if (mysqli_query($conn, $sql)) flash("success", "Note added!");
    else flash("danger", "Error: " . mysqli_error($conn));
    header("Location: index.php"); exit;
}

// Handle Update
if (isset($_POST['action']) && $_POST['action'] === "update") {
    $id = (int)$_POST['id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $sql = "UPDATE notes SET title='$title', description='$desc' WHERE sno=$id";
    if (mysqli_query($conn, $sql)) flash("success", "Note updated!");
    else flash("danger", "Error updating note.");
    header("Location: index.php"); exit;
}

// Handle Delete
if (isset($_POST['action']) && $_POST['action'] === "delete") {
    $id = (int)$_POST['id'];
    $sql = "DELETE FROM notes WHERE sno=$id";
    if (mysqli_query($conn, $sql)) flash("success", "Note deleted!");
    else flash("danger", "Error deleting note.");
    header("Location: index.php"); exit;
}

// ------------------- SEARCH + PAGINATION -------------------
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where = $search ? "WHERE title LIKE '%$search%' OR description LIKE '%$search%'" : '';

$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

$sql = "SELECT * FROM notes $where ORDER BY sno DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);

$count_sql = "SELECT COUNT(*) as total FROM notes $where";
$count_result = mysqli_query($conn, $count_sql);
$total_posts = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_posts / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Notes App</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">
  <h2 class="mb-4 text-center">📒 Notes Application</h2>

  <?php display_flash(); ?>

  <!-- Add Note Form -->
  <form method="POST" class="mb-4 card card-body shadow-sm">
    <input type="hidden" name="action" value="create">
    <div class="mb-2">
      <input type="text" name="title" placeholder="Title" class="form-control" required>
    </div>
    <div class="mb-2">
      <textarea name="description" placeholder="Description" class="form-control" required></textarea>
    </div>
    <button type="submit" class="btn btn-success">Add Note</button>
  </form>

  <!-- Search Form -->
  <form method="GET" action="index.php" class="mb-3 d-flex">
    <input type="text" name="search" placeholder="Search notes..." value="<?php echo htmlspecialchars($search); ?>" class="form-control me-2">
    <button type="submit" class="btn btn-primary">Search</button>
  </form>

  <!-- Notes Listing -->
  <?php if ($result && mysqli_num_rows($result) > 0): ?>
      <?php while ($row = mysqli_fetch_assoc($result)): ?>
          <div class="card mb-3 shadow-sm">
              <div class="card-body">
                  <h5 class="card-title"><?php echo htmlspecialchars($row['title']); ?></h5>
                  <p class="card-text"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>

                  <!-- Edit Form -->
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo $row['sno']; ?>">
                    <input type="hidden" name="title" value="<?php echo htmlspecialchars($row['title']); ?>">
                    <input type="hidden" name="description" value="<?php echo htmlspecialchars($row['description']); ?>">
                    <button type="button" class="btn btn-sm btn-warning" onclick="openEditModal('<?php echo $row['sno']; ?>','<?php echo htmlspecialchars($row['title']); ?>','<?php echo htmlspecialchars($row['description']); ?>')">Edit</button>
                  </form>

                  <!-- Delete Form -->
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $row['sno']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this note?')">Delete</button>
                  </form>
              </div>
          </div>
      <?php endwhile; ?>
  <?php else: ?>
      <p class="text-muted">No notes found.</p>
  <?php endif; ?>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <nav>
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
          <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>

</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="editId">
      <div class="modal-header">
        <h5 class="modal-title">Edit Note</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <input type="text" name="title" id="editTitle" class="form-control" required>
        </div>
        <div class="mb-2">
          <textarea name="description" id="editDescription" class="form-control" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEditModal(id, title, desc) {
    document.getElementById('editId').value = id;
    document.getElementById('editTitle').value = title;
    document.getElementById('editDescription').value = desc;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body>
</html>
