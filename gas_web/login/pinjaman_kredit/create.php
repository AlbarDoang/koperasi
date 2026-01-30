<?php
// login/pinjaman_kredit/create.php
// Step 1: form to collect nama_barang, harga, dp, tenor, and terms acceptance

include '../dashboard/head.php';
include '../dashboard/header.php';
require_once __DIR__ . '/../../config/db.php';

// This page will POST to a local review.php
?>
<div class="container-fluid">
  <div class="row">
    <?php include '../dashboard/menu.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h5>Ajukan Pinjaman Kredit</h5>
      </div>

      <div class="card">
        <div class="card-body">
          <form id="kreditForm" action="review.php" method="POST">
            <input type="hidden" name="id_pengguna" value="<?php echo htmlspecialchars($_SESSION['id_user'] ?? '', ENT_QUOTES); ?>">

            <div class="mb-3">
              <label class="form-label">Nama Barang</label>
              <input class="form-control" name="nama_barang" required>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Harga Barang (Rp)</label>
                <input class="form-control numeric" name="harga" required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">DP (Rp)</label>
                <input class="form-control numeric" name="dp" required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Tenor (bulan)</label>
                <select class="form-control" name="tenor" required>
                  <?php for ($m=1;$m<=12;$m++): ?>
                    <option value="<?php echo $m; ?>"><?php echo $m; ?> bulan</option>
                  <?php endfor; ?>
                </select>
              </div>
            </div>

            <div class="mb-3">
              <input type="checkbox" name="accepted_terms" id="accepted_terms"> <label for="accepted_terms">Saya telah membaca dan menyetujui Syarat & Ketentuan GAS Pinjam</label>
            </div>

            <div class="mb-3">
              <button class="btn btn-primary" id="continueBtn" disabled>Lanjutkan</button>
            </div>
          </form>
        </div>
      </div>

    </main>
  </div>
</div>

<?php include '../dashboard/js.php'; ?>
<script>
// Improved client-side validation + numeric formatting + live simulation with inline messages
function toInt(v){ return parseInt((v||'0').toString().replace(/[^0-9]/g,''),10) || 0; }
function formatRp(n){ return new Intl.NumberFormat('id-ID').format(n); }

function validateInputs(){
  var harga = toInt($('input[name=harga]').val());
  var dp = toInt($('input[name=dp]').val());
  var tenor = parseInt($('select[name=tenor]').val(),10) || 0;
  if (harga <= 0) return 'Harga harus lebih besar dari 0';
  if (dp < 0) return 'DP tidak boleh negatif';
  if (dp >= harga) return 'DP harus lebih kecil dari harga barang';
  if (!Number.isInteger(tenor) || tenor <= 0 || tenor > 12) return 'Pilih tenor antara 1 dan 12 bulan';
  return null;
}

function simulate(){
  var err = validateInputs();
  if (err) {
    $('#sim_result').hide();
    $('#validation_error').text(err).show();
    $('#continueBtn').attr('disabled', true);
    return;
  }
  $('#validation_error').hide();
  $('#continueBtn').attr('disabled', false);

  var harga = toInt($('input[name=harga]').val());
  var dp = toInt($('input[name=dp]').val());
  var tenor = parseInt($('select[name=tenor]').val(),10) || 0;

  var pokok = harga - dp;
  // Syariah flat: equal installments, floor rounding, ignore remainder (waived)
  var base = Math.floor(pokok / Math.max(1, tenor));
  var cicilan = Math.max(0, base);
  var total = (cicilan * Math.max(1, tenor)) + dp; // total payable = DP + cicilan*tenor (difference waived)
  $('#sim_pokok').text(formatRp(pokok));
  $('#sim_cicilan').text(formatRp(cicilan));
  $('#sim_total').text(formatRp(total));
  $('#sim_result').show();
}

$(function(){
  $('input.numeric').on('input', function(){ this.value = this.value.replace(/[^0-9]/g,''); simulate(); });
  $('select[name=tenor]').on('change', function(){ simulate(); });

  $('#kreditForm').on('submit', function(e){
    var err = validateInputs();
    if (err) { alert(err); e.preventDefault(); return; }
    if (!$('#accepted_terms').is(':checked')){
      alert('Anda harus menyetujui Syarat & Ketentuan');
      e.preventDefault();
      return;
    }
  });

  // initialize
  simulate();
});
</script>

<style>
#sim_result { display:none; margin-top:12px; background:#f8f9fa; padding:10px; border-radius:6px; }
#sim_result dt { font-weight:600; }
#validation_error { display:none; color:#b02a37; font-size:0.95rem; margin-top:8px; }
</style>

<style>
#sim_result { display:none; margin-top:12px; background:#f8f9fa; padding:10px; border-radius:6px; }
#sim_result dt { font-weight:600; }
</style>

<div id="hidden_sim" style="display:none">
  <div id="sim_result">
    <dl class="row mb-0">
      <dt class="col-4">Pokok</dt><dd class="col-8" id="sim_pokok">-</dd>
      <dt class="col-4">Cicilan / bulan</dt><dd class="col-8" id="sim_cicilan">-</dd>
      <dt class="col-4">Total bayar</dt><dd class="col-8" id="sim_total">-</dd>
    </dl>
  </div>
  <div id="validation_error"></div>
</div>

<script>
// Move simulation block next to the form fields for visibility
$(function(){
  $('#kreditForm .row').first().after($('#hidden_sim').html());
});
</script>