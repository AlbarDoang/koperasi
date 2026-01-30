<script src="../../../assets/js/jquery.min.js"></script>

<script src="../../../assets/dist/js/bootstrap.bundle.min.js"></script>

<script
  src="https://cdn.jsdelivr.net/npm/chart.js@4.2.1/dist/chart.umd.min.js"
  integrity="sha384-gdQErvCNWvHQZj6XZM0dNsAoY4v+j5P1XDpNkcM3HJG1Yx04ecqIHk7+4VBOCHOG"
  crossorigin="anonymous"></script>

<!-- SELECT2 JS (must load after jQuery) -->
<script src="/gas/gas_web/assets/plugins/select2/js/select2.min.js"></script>

<!-- Dashboard local script (use absolute path to avoid 404s) -->
<script src="/gas/gas_web/login/petugas/dashboard/dashboard.js"></script>

<!-- Ensure Bootstrap Dropdown Works -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Initialize all Bootstrap dropdowns
  var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
  var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
    return new bootstrap.Dropdown(dropdownToggleEl);
  });
  
  // Force show all dropdown items (fix for first-child visibility)
  var dropdownItems = document.querySelectorAll('.navbar .dropdown-item');
  dropdownItems.forEach(function(item) {
    item.style.display = 'block';
    item.style.visibility = 'visible';
    item.style.opacity = '1';
  });
  
  // Debug: log dropdown items count
  console.log('Bootstrap dropdowns initialized:', dropdownList.length);
  console.log('Dropdown items found:', dropdownItems.length);
  dropdownItems.forEach(function(item, index) {
    console.log('Item ' + index + ':', item.textContent.trim());
  });
});
</script>