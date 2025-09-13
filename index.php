<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Cultural Heritage & Tourism — Showcase</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
  <div class="container">
    <a class="navbar-brand" href="#">CHT-DBMS</a>
    <div>
      <a class="btn btn-outline-primary" href="admin/login.php">Admin</a>
    </div>
  </div>
</nav>

<div class="container">
  <h1 class="mb-4">Heritage Sites</h1>

  <div class="mb-3">
    <input id="search" class="form-control" placeholder="Search sites by name or location">
  </div>

  <div id="sites" class="row g-3"></div>
</div>

<!-- Booking Modal -->
<div class="modal fade" id="bookModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="bookingForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Book Visit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="book_site_id">
        <input type="hidden" id="book_event_id">
        <div class="mb-2">
          <label class="form-label">Your Name</label>
          <input id="visitor_name" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Email</label>
          <input id="visitor_email" type="email" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Phone</label>
          <input id="visitor_phone" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">Tickets</label>
          <input id="no_of_tickets" type="number" min="1" value="1" class="form-control">
        </div>
        <div id="book_error" class="text-danger"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" type="submit">Confirm & Pay (Demo)</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const sitesContainer = document.getElementById('sites');
const searchInput = document.getElementById('search');
let currentModal = new bootstrap.Modal(document.getElementById('bookModal'));

async function loadSites(q='') {
  const res = await fetch('api/sites.php?q=' + encodeURIComponent(q));
  const data = await res.json();
  sitesContainer.innerHTML = '';
  data.data.forEach(site => {
    const col = document.createElement('div');
    col.className = 'col-12 col-md-6';
    col.innerHTML = `
      <div class="card">
        <div class="card-body">
          <h5>${site.name} <small class="text-muted">(${site.unesco_status})</small></h5>
          <p class="mb-1"><strong>Where:</strong> ${site.location}</p>
          <p class="mb-1"><strong>Type:</strong> ${site.type} | <strong>Ticket:</strong> ${site.ticket_price}</p>
          <p>${site.description ? site.description.substring(0,200) : ''}</p>
          <div class="mt-2">
            <a class="btn btn-sm btn-outline-primary me-2" href="#" onclick="openBook('${site.site_id}', null)">Book Visit</a>
            <a class="btn btn-sm btn-secondary" href="#" onclick="viewSite(${site.site_id})">View Details</a>
          </div>
        </div>
      </div>
    `;
    sitesContainer.appendChild(col);
  });
}

function openBook(site_id, event_id) {
  document.getElementById('book_site_id').value = site_id || '';
  document.getElementById('book_event_id').value = event_id || '';
  document.getElementById('book_error').innerText = '';
  currentModal.show();
}

searchInput.addEventListener('input', (e) => {
  loadSites(e.target.value);
});

document.getElementById('bookingForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = {
    name: document.getElementById('visitor_name').value,
    email: document.getElementById('visitor_email').value,
    phone: document.getElementById('visitor_phone').value,
    site_id: document.getElementById('book_site_id').value || null,
    event_id: document.getElementById('book_event_id').value || null,
    no_of_tickets: parseInt(document.getElementById('no_of_tickets').value) || 1,
    payment_method: 'online'
  };
  const res = await fetch('api/bookings.php', {
    method: 'POST',
    body: JSON.stringify(payload),
    headers: {'Content-Type': 'application/json'}
  });
  const data = await res.json();
  if (!res.ok) {
    document.getElementById('book_error').innerText = data.error || 'Booking failed';
  } else {
    currentModal.hide();
    alert('Booking successful! Booking ID: ' + data.booking_id + '\nAmount: ' + data.amount);
    loadSites();
  }
});

async function viewSite(id) {
  const res = await fetch('api/site.php?id=' + id);
  const data = await res.json();
  if (!res.ok) { alert(data.error || 'Error'); return; }
  const site = data.site;
  let html = `<h3>${site.name}</h3><p><strong>Location:</strong> ${site.location}</p><p>${site.description}</p>`;
  if (data.events.length) {
    html += '<h4>Upcoming Events</h4><ul>';
    data.events.forEach(ev => {
      html += `<li>${ev.name} (${ev.event_date} ${ev.event_time}) - Ticket: ${ev.ticket_price} - <a href="#" onclick="openBook(null, ${ev.event_id})">Book</a></li>`;
    });
    html += '</ul>';
  }
  if (data.reviews.length) {
    html += '<h4>Reviews</h4><ul>';
    data.reviews.forEach(r => {
      html += `<li><strong>${r.visitor_name}</strong> (${r.review_date}) — Rating: ${r.rating}<br>${r.comment}</li>`;
    });
    html += '</ul>';
  }
  const w = window.open('', '_blank', 'width=700,height=600');
  w.document.write('<html><head><title>' + site.name + '</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="p-3">'+html+'</body></html>');
}

loadSites();
</script>
</body>
</html>
