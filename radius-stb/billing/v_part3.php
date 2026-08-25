<div class="modal fade" id="generateModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST"><input type="hidden" name="action" value="generate">
        <div class="modal-header"><h5 class="modal-title">Generate Voucher Baru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Pilih Paket</label>
                <select name="package_id" class="form-select" required><option value="">-- Pilih Paket --</option>
                <?php foreach ($packages as $pkg): ?>
                <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['name']) ?> - <?= formatRupiah($pkg['price']) ?></option>
                <?php endforeach; ?></select>
            </div>
            <div class="mb-3"><label class="form-label">Jumlah Voucher</label><input type="number" class="form-control" name="quantity" value="10" min="1" max="100" required></div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Panjang Karakter</label>
                    <input type="number" class="form-control" name="char_count" value="8" min="4" max="16" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Jenis Karakter</label>
                    <select name="char_type" class="form-select">
                        <option value="alphanum_upper">Huruf+Angka, Besar (ABC...123...)</option>
                        <option value="alphanum_lower">Huruf+Angka, Kecil (abc...123...)</option>
                        <option value="alphanum_mixed">Huruf+Angka, Campur (AbC...123...)</option>
                        <option value="alpha_upper">Huruf Saja, Besar (ABCDEFG...)</option>
                        <option value="alpha_lower">Huruf Saja, Kecil (abcdefg...)</option>
                        <option value="alpha_mixed">Huruf Saja, Campur (AbCdEfG...)</option>
                        <option value="num">Angka Saja (23456789)</option>
                        <option value="hex">Hex (0-9 a-f)</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="user_eq_pass" id="userEqPass" checked>
                    <label class="form-check-label" for="userEqPass">Username = Password</label>
                </div>
            </div>
            <div class="alert alert-warning"><i class="fas fa-info-circle"></i> Voucher otomatis terdaftar di RADIUS server.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Generate</button></div>
    </form>
</div></div></div>

<script>
function toggleGroup(g){
    var rows=document.querySelectorAll('.gv.'+g);
    var icon=document.getElementById('ig'+g);
    var show=rows[0]?rows[0].style.display:'none';
    rows.forEach(function(r){r.style.display=show==='none'?'':'none';});
    if(icon){icon.className=show==='none'?'fas fa-chevron-right':'fas fa-chevron-down';}
}
function toggleSelectAll(cb){
    document.querySelectorAll('.vcb').forEach(function(c){
        if(c.closest('tr').style.display!=='none')c.checked=cb.checked;
    });
    updateCount();
}
function updateCount(){
    var n=0;document.querySelectorAll('.vcb:checked').forEach(function(){n++;});
    document.getElementById('countSelected').textContent=n;
}
function bulkDelete(){
    var ids=[];
    document.querySelectorAll('.vcb:checked').forEach(function(c){ids.push(c.value);});
    if(ids.length===0){alert('Pilih voucher terlebih dahulu!');return;}
    if(!confirm('Hapus '+ids.length+' voucher?'))return;
    document.getElementById('bulkForm').submit();
}
function printSelected(){
    var ids=[];
    document.querySelectorAll('.vcb:checked').forEach(function(c){ids.push(c.value);});
    if(ids.length===0){alert('Pilih voucher untuk dicetak!');return;}
    var w=window.open('','_blank','width=800,height=600');
    var h='<html><head><title>Cetak Voucher</title><style>';
    h+='body{font-family:Arial,sans-serif;padding:20px;font-size:12px}';
    h+='.vp{border:1px solid #333;padding:10px;margin:6px 0;page-break-inside:avoid}';
    h+='.vc{font-size:16px;font-weight:bold;margin:4px 0}';
    h+='h2{text-align:center;margin-bottom:20px}';
    h+='@media print{button{display:none}}';
    h+='</style></head><body>';
    h+='<button onclick="window.print()">Cetak</button><hr>';
    h+='<h2>Voucher WiFi Hotspot</h2>';
    document.querySelectorAll('.vcb:checked').forEach(function(c){
        var tr=c.closest('tr');
        var tds=tr.querySelectorAll('td');
        h+='<div class="vp">';
        h+='<div class="vc">Username: '+tds[3].textContent.trim()+'</div>';
        h+='<div class="vc">Password: '+tds[4].textContent.trim()+'</div>';
        h+='<div>Paket: '+tds[2].textContent.trim()+' | Kode: '+tds[1].textContent.trim()+'</div>';
        h+='</div>';
    });
    h+='</body></html>';
    w.document.write(h);w.document.close();
}
</script>

<?php require_once 'includes/footer.php'; ?>
