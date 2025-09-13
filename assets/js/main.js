// assets/js/main.js
function confirmDelete(url){ if (confirm('Are you sure? This action cannot be undone.')) { window.location = url; } }


// simple client-side helper to open a centered popup (used for CSV export preview etc.)
function popup(url, title='Popup', w=900, h=600){ const left = (screen.width/2)-(w/2); const top = (screen.height/2)-(h/2); window.open(url, title, `toolbar=no, location=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=${w}, height=${h}, top=${top}, left=${left}`); }