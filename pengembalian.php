<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Riwayat Pengembalian & Denda';

$stmtR = $pdo->query("
    SELECT p.*, a.nama AS nama_anggota, a.institusi, b.judul, b.kode_buku 
    FROM peminjaman p
    JOIN anggota a ON p.anggota_id = a.id
    JOIN buku b ON p.buku_id = b.id
    WHERE p.status = 'Kembali'
    ORDER BY p.tanggal_kembali DESC, p.id DESC
");
$riwayatKembali = $stmtR->fetchAll();

$totalDendaTerkumpul = $pdo->query("SELECT COALESCE(SUM(denda), 0) FROM peminjaman WHERE status = 'Kembali'")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Riwayat Pengembalian & Denda</h2>
        <p class="text-sm text-slate-500">Rekap transaksi yang telah selesai beserta rekapitulasi denda keterlambatan.</p>
    </div>
    <div class="bg-emerald-100 text-emerald-800 px-4 py-2 rounded-xl border border-emerald-300 font-bold text-sm">
        Total Denda Masuk: Rp <?= number_format((float)$totalDendaTerkumpul, 0, ',', '.') ?>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-slate-700 font-semibold border-b">
                <tr>
                    <th class="p-3">Kode Trx</th>
                    <th class="p-3">Peminjam</th>
                    <th class="p-3">Buku Dikembalikan</th>
                    <th class="p-3">Tgl Pinjam</th>
                    <th class="p-3">Tgl Kembali</th>
                    <th class="p-3 text-center">Terlambat</th>
                    <th class="p-3 text-right">Denda</th>
                    <th class="p-3">Petugas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($riwayatKembali)): ?>
                <tr><td colspan="8" class="p-6 text-center text-slate-400">Belum ada riwayat pengembalian buku.</td></tr>
                <?php else: foreach ($riwayatKembali as $r): ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-3 font-mono text-xs"><?= e($r['kode_transaksi']) ?></td>
                    <td class="p-3">
                        <p class="font-bold text-slate-800"><?= e($r['nama_anggota']) ?></p>
                        <p class="text-xs text-slate-500"><?= e($r['institusi']) ?></p>
                    </td>
                    <td class="p-3">
                        <p class="font-semibold text-slate-800"><?= e($r['judul']) ?></p>
                        <p class="text-xs font-mono text-slate-500"><?= e($r['kode_buku']) ?></p>
                    </td>
                    <td class="p-3 text-xs"><?= date('d/m/Y', strtotime($r['tanggal_pinjam'])) ?></td>
                    <td class="p-3 text-xs font-semibold text-emerald-700"><?= date('d/m/Y', strtotime($r['tanggal_kembali'])) ?></td>
                    <td class="p-3 text-center">
                        <?php if ($r['hari_terlambat'] > 0): ?>
                            <span class="text-xs px-2 py-0.5 rounded font-bold bg-rose-100 text-rose-700"><?= $r['hari_terlambat'] ?> Hari</span>
                        <?php else: ?>
                            <span class="text-xs px-2 py-0.5 rounded text-emerald-700 bg-emerald-50">Tepat Waktu</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-right font-mono font-bold <?= $r['denda'] > 0 ? 'text-rose-600' : 'text-slate-500' ?>">
                        Rp <?= number_format($r['denda'], 0, ',', '.') ?>
                    </td>
                    <td class="p-3 text-xs text-slate-600"><?= e($r['petugas']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
