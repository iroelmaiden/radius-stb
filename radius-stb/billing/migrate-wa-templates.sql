-- WhatsApp message templates
CREATE TABLE IF NOT EXISTS wa_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(50) NOT NULL,
    subject VARCHAR(100) DEFAULT NULL,
    message TEXT NOT NULL,
    variables TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO wa_templates (template_name, subject, message, variables) VALUES
('tagihan', 'Tagihan Internet', 'Yth {nama},\n\nTagihan internet Anda:\nPeriode: {periode}\nJumlah: {amount}\nJatuh Tempo: {due_date}\n\nMohon lakukan pembayaran sebelum jatuh tempo.\nTerima kasih.', 'nama,periode,amount,due_date'),
('isolir', 'Isolir Akun', 'Yth {nama},\n\nAkun internet Anda telah DIISOLIR karena tagihan belum terbayar.\nSilahkan lakukan pembayaran untuk mengaktifkan kembali layanan.\n\nTerima kasih.', 'nama'),
('bayar', 'Pembayaran Diterima', 'Yth {nama},\n\nPembayaran internet Anda telah diterima:\nPeriode: {periode}\nJumlah: {amount}\n\nLayanan Anda telah aktif kembali.\nTerima kasih.', 'nama,periode,amount'),
('welcome', 'Selamat Datang', 'Yth {nama},\n\nSelamat datang di layanan internet kami.\nUsername: {username}\nPassword: {password}\nProfile: {package}\n\nGunakan layanan dengan bijak.\nTerima kasih.', 'username,password,package,nama');
