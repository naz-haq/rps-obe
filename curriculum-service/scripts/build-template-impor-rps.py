#!/usr/bin/env python3
"""Bangun template Excel impor RPS lama (4 sheet + petunjuk).

Memakai XlsxWriter (menyimpan string di sharedStrings) agar kompatibel dengan
pustaka pembaca read-excel-file di frontend.

Output: curriculum-web/public/template-impor-rps.xlsx
Jalankan ulang bila kolom template berubah:
    python3 curriculum-service/scripts/build-template-impor-rps.py
"""
from pathlib import Path

import xlsxwriter


def sheet(wb, hdr_fmt, cell_fmt, title, columns, examples, widths):
    ws = wb.add_worksheet(title)
    ws.freeze_panes(1, 0)
    for c, name in enumerate(columns):
        ws.write_string(0, c, name, hdr_fmt)
        ws.set_column(c, c, widths[c])
    for r, row in enumerate(examples, start=1):
        for c, val in enumerate(row):
            if isinstance(val, (int, float)):
                ws.write_number(r, c, val, cell_fmt)
            else:
                ws.write_string(r, c, str(val), cell_fmt)


def main():
    out = Path(__file__).resolve().parents[2] / "curriculum-web" / "public" / "template-impor-rps.xlsx"
    out.parent.mkdir(parents=True, exist_ok=True)

    wb = xlsxwriter.Workbook(str(out))
    hdr = wb.add_format({"bold": True, "font_color": "#FFFFFF", "bg_color": "#1F3A5F", "border": 1, "text_wrap": True, "valign": "top"})
    cel = wb.add_format({"border": 1, "text_wrap": True, "valign": "top"})
    note = wb.add_format({"font_color": "#6B7280", "text_wrap": True, "valign": "top"})
    judul = wb.add_format({"bold": True, "font_size": 14, "font_color": "#1F3A5F"})

    ws = wb.add_worksheet("Petunjuk")
    ws.set_column(0, 0, 110)
    ws.write_string(0, 0, "Template Impor RPS Lama", judul)
    petunjuk = [
        "Isi tiap sheet sesuai kolomnya. Baris pertama (judul kolom) JANGAN dihapus.",
        "Kosongkan sheet yang tidak ingin diimpor — hanya sheet berisi yang akan menimpa tahap tsb.",
        "Kolom bertanda (pisah ;) boleh berisi banyak nilai, dipisah tanda titik-koma. Contoh: CPL-01; CPL-03",
        "",
        "Sheet CPMK       : Kode | Deskripsi | Kode CPL (pisah ;) | Taksonomi (pisah ;)",
        "Sheet Sub-CPMK   : Kode | Kode CPMK Induk | Deskripsi | Taksonomi (pisah ;) | Indikator (pisah ;)",
        "Sheet Mingguan   : Minggu | Kode Sub-CPMK | Indikator | Kriteria | Metode | Bentuk Luring | Bentuk Daring | Pengalaman | Materi/Pustaka | Bobot(%)",
        "Sheet Penilaian  : Nama | Jenis | Bobot(%) | Kode Sub-CPMK | Minggu | Instrumen",
        "",
        "Kode CPMK/Sub-CPMK harus konsisten antar sheet agar keterkaitan terbaca (mis. Sub-CPMK1.1 merujuk CPMK1).",
        "Taksonomi contoh: C4, A3, P2. Jenis penilaian contoh: tugas, kuis, uts, uas, proyek, praktik.",
        "Setelah impor, tinjau tiap tab di aplikasi lalu klik Commit RPS.",
    ]
    for i, text in enumerate(petunjuk, start=2):
        ws.write_string(i, 0, text, note)

    sheet(
        wb, hdr, cel, "CPMK",
        ["Kode", "Deskripsi", "Kode CPL (pisah ;)", "Taksonomi (pisah ;)"],
        [
            ["CPMK1", "Mampu menganalisis mekanisme kerja obat pada sistem organ.", "CPL-03; CPL-05", "C4"],
            ["CPMK2", "Mampu merancang rencana terapi rasional berbasis bukti.", "CPL-05", "C5"],
        ],
        [14, 60, 22, 20],
    )

    sheet(
        wb, hdr, cel, "Sub-CPMK",
        ["Kode", "Kode CPMK Induk", "Deskripsi", "Taksonomi (pisah ;)", "Indikator (pisah ;)"],
        [
            ["Sub-CPMK1.1", "CPMK1", "Menjelaskan farmakodinamik golongan obat kardiovaskular.", "C3", "Ketepatan menjelaskan mekanisme; Kelengkapan contoh"],
            ["Sub-CPMK1.2", "CPMK1", "Menganalisis interaksi obat pada kasus.", "C4", "Ketepatan analisis interaksi"],
        ],
        [16, 16, 55, 20, 40],
    )

    sheet(
        wb, hdr, cel, "Mingguan",
        [
            "Minggu", "Kode Sub-CPMK", "Indikator", "Kriteria Penilaian", "Metode Pembelajaran",
            "Bentuk Luring", "Bentuk Daring", "Pengalaman Belajar", "Materi / Pustaka", "Bobot (%)",
        ],
        [
            [1, "Sub-CPMK1.1", "Ketepatan menjelaskan", "Rubrik deskriptif", "Ceramah, diskusi", "Kuliah tatap muka", "LMS", "Studi kasus", "Farmakologi Dasar — Bab 1 [Pustaka: 1]", 5],
            [2, "Sub-CPMK1.2", "Ketepatan analisis", "Rubrik analitik", "PBL", "Diskusi kelompok", "-", "Analisis kasus", "Bab 2 [Pustaka: 1,2]", 5],
        ],
        [8, 16, 24, 22, 22, 20, 16, 22, 32, 9],
    )

    sheet(
        wb, hdr, cel, "Penilaian",
        ["Nama Komponen", "Jenis", "Bobot (%)", "Kode Sub-CPMK", "Minggu", "Instrumen"],
        [
            ["Tugas Analisis Kasus", "tugas", 20, "Sub-CPMK1.2", 4, "Laporan analisis"],
            ["Ujian Tengah Semester", "uts", 30, "Sub-CPMK1.1", 8, "Soal esai"],
            ["Ujian Akhir Semester", "uas", 50, "Sub-CPMK1.2", 16, "Soal esai"],
        ],
        [26, 12, 10, 16, 9, 26],
    )

    wb.close()
    print("Tersimpan:", out)


if __name__ == "__main__":
    main()
