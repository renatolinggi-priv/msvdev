<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Testseite – Modernes Bootstrap Layout</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts: Inter -->
  <link href="https://fonts.googleapis.com/css?family=Inter:400,700&display=swap" rel="stylesheet">
  <!-- Eigenes CSS -->
  <style>
    body {
      font-family: 'Inter', Arial, sans-serif;
      font-size: 1.08rem;
      line-height: 1.6;
      background: #f9f9fa;
      padding-top: 72px; /* Platz für Navbar */
    }
    .navbar-brand {
      font-weight: bold;
      letter-spacing: 1px;
    }
    footer {
      font-size: 0.95em;
      color: #666;
      margin-top: 3em;
    }
    .card {
      border-radius: 1.25rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    h1, h2, h3 {
      margin-top: 1.5em;
      margin-bottom: 0.5em;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">Mein Testprojekt</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link active" href="#">Start</a></li>
          <li class="nav-item"><a class="nav-link" href="#">Features</a></li>
          <li class="nav-item"><a class="nav-link" href="#">Kontakt</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hauptbereich -->
  <main class="container my-5">
    <div class="row">
      <section class="col-12 col-md-8">
        <h1>Willkommen auf der Testseite!</h1>
        <p>Hier probierst Du ein modernes Bootstrap-Layout aus. Das Ziel: Sauber, übersichtlich, einfach schön.</p>
        <div class="card mb-4">
          <div class="card-body">
            <h2 class="card-title">Das ist eine Card 🎉</h2>
            <p class="card-text">Cards sind perfekt, um Infos schick darzustellen. Sie sind abgerundet, haben einen Schatten und lassen sich super kombinieren.</p>
            <a href="#" class="btn btn-primary">Mehr erfahren</a>
          </div>
        </div>

        <h2>Tabelle (modern)</h2>
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>1</td>
              <td>Anna Muster</td>
              <td><span class="badge bg-success">Aktiv</span></td>
            </tr>
            <tr>
              <td>2</td>
              <td>Peter Beispiel</td>
              <td><span class="badge bg-secondary">Inaktiv</span></td>
            </tr>
          </tbody>
        </table>
      </section>
      <aside class="col-12 col-md-4">
        <div class="card mb-4">
          <div class="card-body">
            <h3>Sidebar</h3>
            <p>Kurzinfos, Links oder weitere Features kannst Du hier platzieren.</p>
          </div>
        </div>
      </aside>
    </div>
  </main>

  <footer class="bg-light text-center py-3">
    &copy; 2025 Renato – Testprojekt
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
