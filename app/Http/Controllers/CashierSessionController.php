<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Transaksi;
use App\Models\CashMovement;
use Illuminate\Http\Request;
use App\Models\CashierSession;
use App\Models\MainCashBalance;
use App\Models\MainCashMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashierSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = CashierSession::with(['user', 'transaksi', 'cash_movement.user'])->get();

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validasi request
        $request->validate([
            'user_id' => 'required|exists:users,id', // Pastikan user_id ini ada di tabel users
            'starting_cash_amount' => 'required|numeric|min:0',
            // Tambahkan validasi lain jika ada kolom lain di cashier_sessions yang perlu diisi
        ]);

        // 2. Cek apakah user_id ini sudah memiliki sesi kasir yang aktif
        // Ini penting untuk mencegah user membuka lebih dari satu sesi di waktu yang sama
        $existingSession = CashierSession::where('user_id', $request->user_id)
            ->where('status', 'open')
            ->first();
        
        if ($existingSession) {
            return response()->json([
                'success' => false,
                'message' => 'Kasir ini sudah memiliki sesi aktif. Silakan tutup sesi sebelumnya.'
            ], 400); // 400 Bad Request
        }

        // 3. Pastikan brankas utama memiliki saldo yang cukup untuk penarikan
        $mainCashBalance = MainCashBalance::find(1); // Ganti 1 dengan ID akun brankas utama yang sebenarnya
        if (!$mainCashBalance || $mainCashBalance->current_balance < $request->starting_cash_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Saldo brankas utama tidak mencukupi untuk modal awal ini.'
            ], 400);
        }
        
        // Menggunakan transaksi database untuk memastikan atomicity
        // Jika ada bagian dari blok ini yang gagal, semua akan di-rollback
        DB::beginTransaction();
        try {
            // 4. Buat record di `cashier_sessions`
            // Ambil data yang dibutuhkan dari request, dan tambahkan `shift_start_time`
            $cashier_session = CashierSession::create([
                'user_id' => $request->user_id,
                'shift_start_time' => Carbon::now(),
                'starting_cash_amount' => $request->starting_cash_amount,
                'status' => 'open',
                // Pastikan kolom lain yang 'nullable' tidak perlu diisi di sini
                // atau jika ada default value, biarkan Laravel yang mengisi
            ]);

            CashMovement::create([
                'cashier_session_id' => $cashier_session->id,
                'user_id' => $request->user()->id,
                'type' => 'initial_deposit', 
                'amount' => $request->starting_cash_amount,
                'description' => 'Modal Awal',
            ]);

            // 5. Buat record `main_cash_movement` untuk penarikan modal awal
            // `initiated_by_user_id` sebaiknya dari `Auth::id()` jika yang melakukan action adalah user yang login
            // atau `user_id` dari kasir itu sendiri jika kasir yang memicu aksinya
            MainCashMovement::create([
                'main_cash_balance_id' => 1, // Ganti 1 dengan ID akun brankas utama Anda
                'transaction_type' => 'WITHDRAWAL', // Konsisten dengan konsep penarikan
                'amount' => $request->starting_cash_amount,
                'description' => 'Penarikan modal awal untuk sesi kasir ' . $cashier_session->id,
                'initiated_by_user_id' => $request->user()->id, // User yang sedang login (manajer/kasir)
                'reference_id' => $cashier_session->id, // Penting: Link ke sesi kasir yang baru dibuat
            ]);

            MainCashBalance::where('id', 1)->decrement('current_balance', $request->starting_cash_amount);

            // 6. Committing transaksi jika semua langkah berhasil
            DB::commit();

            // 7. Mengambil data sesi kasir yang baru dibuat
            // Anda sudah memiliki `$cashier_session` dari langkah `create` di atas, tidak perlu query lagi
            return response()->json([
                'success' => true,
                'message' => 'Sesi kasir berhasil dibuka.',
                'data' => $cashier_session,
            ], 200);

        } catch (\Exception $e) {
            // 8. Rollback transaksi jika ada error
            DB::rollBack();
            Log::error('Gagal membuka sesi kasir: ' . $e->getMessage()); // Log error untuk debugging

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuka sesi kasir: ' . $e->getMessage(),
            ], 500); // 500 Internal Server Error
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(CashierSession $cashierSession)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CashierSession $cashierSession)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CashierSession $cashierSession)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $cashierSessionId)
    {
        // 3. Ambil Sesi Kasir yang Akan Ditutup
        $cashierSession = CashierSession::find($cashierSessionId);

        // Cek apakah sesi ada dan dalam status 'open'
        if (!$cashierSession || $cashierSession->status !== 'open') {
            return response()->json(['message' => 'Sesi kasir tidak ditemukan atau sudah ditutup/ditinggalkan.'], 404);
        }

        // buat record untuk uang tutup kasir
        CashMovement::create([
            'cashier_session_id' => $cashierSessionId,
            'user_id' => $request->user()->id,
            'type' => 'final_deposit',
            'amount' => $request->ending_cash_amount,
            'description' => 'Uang tutup kasir',
        ]);

        // 4. Hitung Ulang Semua Total Penjualan dari Tabel `transaksis`
        // Ini adalah langkah KRUSIAL untuk akurasi data
        $totalSalesCash = Transaksi::where('cashier_session_id', $cashierSessionId)
                                    ->where('payment_method', 'cash')
                                    ->sum('total_amount'); // Gunakan kolom yang benar untuk total_amount

        $totalSalesEWallet = Transaksi::where('cashier_session_id', $cashierSessionId)
                                    ->where('payment_method', 'e-wallet')
                                    ->sum('total_amount');

        $totalSalesTransferBank = Transaksi::where('cashier_session_id', $cashierSessionId)
                                        ->where('payment_method', 'transfer_bank')
                                        ->sum('total_amount');

        $totalSalesQris = Transaksi::where('cashier_session_id', $cashierSessionId)
                                ->where('payment_method', 'qris')
                                ->sum('total_amount');

        // Asumsi 'total_sales_gerai' adalah metode pembayaran lain yang terkait dengan gerai
        $totalSalesGerai = Transaksi::where('cashier_session_id', $cashierSessionId)
                                ->where('payment_method', 'gerai') // Asumsi ada payment_method 'gerai'
                                ->sum('total_amount');

        // 5. Hitung Ulang Total Cash In dan Cash Out dari Tabel `cash_movements`
        $totalCashIn = CashMovement::where('cashier_session_id', $cashierSessionId)
                                ->where('type', 'in')
                                ->sum('amount');

        // `initial_deposit` dari `cash_movements` juga bisa masuk ke `total_cash_in`
        // Jika `initial_deposit` di `cashier_sessions` adalah `starting_cash_amount`,
        // maka `cash_movements.type='initial_deposit'` mungkin tidak perlu dihitung di sini
        // (karena sudah dihitung sebagai `starting_cash_amount`).
        // Pertimbangkan kembali apakah `initial_deposit` di `cash_movements` ini merujuk ke apa.
        // Jika itu adalah "setoran kas tambahan di tengah shift", maka masuk ke `total_cash_in`.
        // Jika itu adalah "modal awal", maka tidak perlu dihitung lagi di sini.
        // Untuk amannya, mari kita asumsikan 'initial_deposit' di `cash_movements` adalah setoran tambahan.
        // $totalCashIn += CashMovement::where('cashier_session_id', $cashierSessionId)
        //                         ->where('type', 'initial_deposit')
        //                         ->sum('amount');


        $totalCashOut = CashMovement::where('cashier_session_id', $cashierSessionId)
                                    ->where('type', 'out')
                                    ->sum('amount');
        
        // 6. Hitung `expected_ending_cash_amount` (Jumlah Tunai yang Seharusnya Ada di Laci)
        // Rumus: Modal Awal + Total Penjualan Tunai + Total Cash In - Total Cash Out
        $expectedEndingCashAmount = $cashierSession->starting_cash_amount +
                                    $totalSalesCash +
                                    $totalCashIn -
                                    $totalCashOut;

        // 7. Ambil `ending_cash_amount` dari Frontend
        $ending_cash_amount = $request->input('ending_cash_amount');

        // 8. Hitung Selisih (Selisih Kurang/Lebih)
        $cashDifference = $ending_cash_amount - $expectedEndingCashAmount;

        // 9. Perbarui Sesi Kasir di Database
        $cashierSession->shift_end_time           = now(); // Waktu saat ini di server
        $cashierSession->ending_cash_amount       = $ending_cash_amount; // Ini adalah uang fisik yang dilaporkan kasir
        $cashierSession->total_sales_cash         = $totalSalesCash;
        $cashierSession->total_sales_e_wallet     = $totalSalesEWallet;
        $cashierSession->total_sales_transfer_bank= $totalSalesTransferBank;
        $cashierSession->total_sales_qris         = $totalSalesQris;
        $cashierSession->total_sales_gerai        = $totalSalesGerai; // Jika relevan
        $cashierSession->total_cash_in            = $totalCashIn;
        $cashierSession->total_cash_out           = $totalCashOut;
        $cashierSession->status                   = 'closed'; // Ubah status menjadi 'closed'

        // Tambahkan kolom baru untuk menyimpan selisih jika ada (opsional tapi sangat direkomendasikan)
        $cashierSession->cash_difference          = $cashDifference; // Perlu menambahkan kolom ini di migration
        // Misal: $table->decimal('cash_difference', 10, 2)->nullable();

        $cashierSession->save();

        // 10. (PENTING!) Buat `main_cash_movement` untuk setoran kasir ke kas global
        // Ini adalah saat uang fisik dari laci kasir "dipindahkan" ke brankas/bank utama
        // Hanya jika ada uang tunai yang disetor (misal, ending_physical_cash_amount - modal awal yang akan disisakan)
        // $amountToDepositToMainCash = $ending_cash_amount - $cashierSession->starting_cash_amount; // Asumsi modal awal selalu disisakan. Jika tidak, $ending_cash_amount.
                                                                                                      // Logika ini perlu disesuaikan dengan kebijakan setoran Anda.
                                                                                                      // Umumnya, jika uang dari laci dikosongkan/disetor semua: $amountToDepositToMainCash = $ending_cash_amount.
                                                                                                      // Atau, jika ada selisih, selisih juga harus diakomodasi.
        // saya menggunakan konsep 100% setor ke brankas tiap tutup shift, bukan 'menyisakan'
        $amountToDepositToMainCash = $ending_cash_amount;

        if ($amountToDepositToMainCash > 0) {
            $mainCashMovement = new MainCashMovement();
            $mainCashMovement->main_cash_balance_id = 1; // Brankas utama (default)
            $mainCashMovement->transaction_type = 'DEPOSIT';
            $mainCashMovement->amount = $amountToDepositToMainCash;
            $mainCashMovement->description = 'Setoran penjualan dari sesi kasir ' . $cashierSessionId . ' - Kasir ' . $cashierSession->user->name;
            $mainCashMovement->initiated_by_user_id = $request->user()->id; // User yang sedang login (supervisor/kasir)
            $mainCashMovement->reference_id = $cashierSessionId; // Link ke sesi kasir
            $mainCashMovement->save();

            // **Update saldo di main_cash_balances (ini akan otomatis dilakukan oleh observer/trigger/logic setelah mainCashMovement tersimpan)**
            MainCashBalance::where('id', $mainCashMovement->main_cash_balance_id)->increment('current_balance', $mainCashMovement->amount);
        }
        // Catatan: Selisih kas (`cashDifference`) harus ditangani terpisah dalam `main_cash_movements`
        // sebagai `DEPOSIT` (jika lebih) atau `EXPENSE` (jika kurang) atau `ADJUSTMENT` ke akun `cash_difference`
        // atau `shrinkage_account` di `main_cash_balances` untuk audit. Ini opsional tapi bagus untuk akuntansi.
        if ($cashDifference != 0) {
            $mainCashMovement = new MainCashMovement();
            $mainCashMovement->amount = $amountToDepositToMainCash;
            $mainCashMovement->description = 'Setoran penjualan dari sesi kasir ' . $cashierSessionId . ' - Kasir ' . $cashierSession->user->name;
            $mainCashMovement->initiated_by_user_id = $request->user()->id; // User yang sedang login (supervisor/kasir)
            $mainCashMovement->reference_id = $cashierSessionId; // Link ke sesi kasir
            
            if ($cashDifference > 0) {
                $mainCashMovement->main_cash_balance_id = 2; 
                $mainCashMovement->transaction_type = 'deposit';
            } else if ($cashDifference < 0) {
                $mainCashMovement->main_cash_balance_id = 3; 
                $mainCashMovement->transaction_type = 'expense';
            }

            $mainCashMovement->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesi kasir berhasil ditutup.',
            'data' => $cashierSession,
            'cash_difference' => $cashDifference, // Kirim selisih ke frontend
            'expected_ending_cash_amount' => $expectedEndingCashAmount, // Kirim nilai ekspektasi juga
        ]);
    }
}
