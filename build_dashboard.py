import openpyxl
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side, numbers
from openpyxl.chart import BarChart, PieChart, Reference
from openpyxl.chart.label import DataLabelList
from openpyxl.chart.series import DataPoint
from openpyxl.utils import get_column_letter
from datetime import datetime

wb = openpyxl.load_workbook(r"C:\Users\User\Documents\PPPK.xlsx")

if "Dashboard Analisa" in wb.sheetnames:
    del wb["Dashboard Analisa"]

ws = wb.create_sheet("Dashboard Analisa", 0)

# Colors
DARK_BLUE = "1F4E79"
MED_BLUE = "2E75B6"
LIGHT_BLUE = "D6E4F0"
ACCENT_GREEN = "548235"
ACCENT_ORANGE = "ED7D31"
WHITE = "FFFFFF"
LIGHT_GRAY = "F2F2F2"
DARK_GRAY = "404040"

title_font = Font(name="Calibri", size=18, bold=True, color=WHITE)
subtitle_font = Font(name="Calibri", size=11, italic=True, color=WHITE)
section_font = Font(name="Calibri", size=13, bold=True, color=WHITE)
header_font = Font(name="Calibri", size=11, bold=True, color=WHITE)
data_font = Font(name="Calibri", size=11, color=DARK_GRAY)
data_bold_font = Font(name="Calibri", size=11, bold=True, color=DARK_GRAY)
card_label_font = Font(name="Calibri", size=10, bold=True, color=WHITE)
card_value_font = Font(name="Calibri", size=20, bold=True, color=WHITE)
pct_font = Font(name="Calibri", size=11, color=DARK_GRAY)

title_fill = PatternFill(start_color=DARK_BLUE, end_color=DARK_BLUE, fill_type="solid")
section_fill = PatternFill(start_color=MED_BLUE, end_color=MED_BLUE, fill_type="solid")
header_fill = PatternFill(start_color=MED_BLUE, end_color=MED_BLUE, fill_type="solid")
light_fill = PatternFill(start_color=LIGHT_BLUE, end_color=LIGHT_BLUE, fill_type="solid")
alt_fill = PatternFill(start_color=LIGHT_GRAY, end_color=LIGHT_GRAY, fill_type="solid")
card_fills = [
    PatternFill(start_color="1F4E79", end_color="1F4E79", fill_type="solid"),
    PatternFill(start_color="2E75B6", end_color="2E75B6", fill_type="solid"),
    PatternFill(start_color="548235", end_color="548235", fill_type="solid"),
    PatternFill(start_color="ED7D31", end_color="ED7D31", fill_type="solid"),
    PatternFill(start_color="7030A0", end_color="7030A0", fill_type="solid"),
    PatternFill(start_color="C00000", end_color="C00000", fill_type="solid"),
]

center = Alignment(horizontal="center", vertical="center", wrap_text=True)
left_center = Alignment(horizontal="left", vertical="center", wrap_text=True)
right_center = Alignment(horizontal="right", vertical="center")

thin_border = Border(
    left=Side(style="thin", color="B4C6E7"),
    right=Side(style="thin", color="B4C6E7"),
    top=Side(style="thin", color="B4C6E7"),
    bottom=Side(style="thin", color="B4C6E7"),
)

# Column widths
col_widths = {"A": 35, "B": 10, "C": 10, "D": 10, "E": 12, "F": 5, "G": 35, "H": 10, "I": 10, "J": 10, "K": 12, "L": 5}
for col, w in col_widths.items():
    ws.column_dimensions[col].width = w

# === ROW 1: Title ===
ws.merge_cells("A1:F1")
ws["A1"] = "DASHBOARD ANALISA DATA KEPEGAWAIAN P3K"
ws["A1"].font = title_font
ws["A1"].fill = title_fill
ws["A1"].alignment = center
for c in range(1, 7):
    ws.cell(row=1, column=c).fill = title_fill

ws.merge_cells("G1:L1")
ws["G1"] = "UNIVERSITAS MATARAM"
ws["G1"].font = title_font
ws["G1"].fill = title_fill
ws["G1"].alignment = center
for c in range(7, 13):
    ws.cell(row=1, column=c).fill = title_fill

ws.row_dimensions[1].height = 40

# === ROW 2: Subtitle ===
ws.merge_cells("A2:L2")
ws["A2"] = f"Terakhir diperbarui: {datetime.now().strftime('%d/%m/%Y %H:%M')}"
ws["A2"].font = subtitle_font
ws["A2"].fill = PatternFill(start_color="2B5797", end_color="2B5797", fill_type="solid")
ws["A2"].alignment = center
for c in range(1, 13):
    ws.cell(row=2, column=c).fill = PatternFill(start_color="2B5797", end_color="2B5797", fill_type="solid")
ws.row_dimensions[2].height = 25

# === ROW 3: Blank ===
ws.row_dimensions[3].height = 10

# === ROW 4-5: Summary Cards ===
card_labels = ["TOTAL PEGAWAI", "LAKI-LAKI", "PEREMPUAN", "UNIT KERJA", "JENIS STATUS", "LOG DATA"]
card_formulas = [
    "=COUNTA(Database!D2:D1151)",
    '=COUNTIF(Database!G:G,"L")',
    '=COUNTIF(Database!G:G,"P")',
    "=COUNTA(Master!N2:N22)",
    "=COUNTA(Master!C2:C7)",
    "=COUNTA('LOG DATA'!C2:C100)",
]

for i, (label, formula) in enumerate(zip(card_labels, card_formulas)):
    col = i + 1
    cell_label = ws.cell(row=4, column=col, value=label)
    cell_label.font = card_label_font
    cell_label.fill = card_fills[i]
    cell_label.alignment = center
    cell_label.border = thin_border

    cell_val = ws.cell(row=5, column=col, value=formula)
    cell_val.font = card_value_font
    cell_val.fill = card_fills[i]
    cell_val.alignment = center
    cell_val.border = thin_border
    cell_val.number_format = '#,##0'

ws.row_dimensions[4].height = 25
ws.row_dimensions[5].height = 40

# === ROW 6: Blank ===
ws.row_dimensions[6].height = 10

# === SECTION: Distribusi Status Kepegawaian ===
row_s = 7
ws.merge_cells(f"A{row_s}:E{row_s}")
ws.cell(row=row_s, column=1, value="DISTRIBUSI BERDASARKAN STATUS KEPEGAWAIAN").font = section_font
ws.cell(row=row_s, column=1).fill = section_fill
ws.cell(row=row_s, column=1).alignment = left_center
for c in range(1, 6):
    ws.cell(row=row_s, column=c).fill = section_fill
ws.row_dimensions[row_s].height = 30

row_h = row_s + 1
headers_sk = ["Status Kepegawaian", "Laki-laki", "Perempuan", "Total", "% of Total"]
for i, h in enumerate(headers_sk):
    cell = ws.cell(row=row_h, column=i+1, value=h)
    cell.font = header_font
    cell.fill = header_fill
    cell.alignment = center
    cell.border = thin_border
ws.row_dimensions[row_h].height = 25

status_list = [
    "P3K Tendik Full Time",
    "P3K Tendik Paruh Waktu",
    "P3K Dosen Full Time",
    "P3K Dosen Paruh Waktu",
    "P3K Nakes Paruh Waktu",
    "P3K Nakes Full Time",
]

for idx, status in enumerate(status_list):
    r = row_h + 1 + idx
    ws.cell(row=r, column=1, value=status).font = data_font
    ws.cell(row=r, column=1).alignment = left_center
    ws.cell(row=r, column=1).border = thin_border

    ws.cell(row=r, column=2, value=f'=COUNTIFS(Database!Q:Q,"{status}",Database!G:G,"L")')
    ws.cell(row=r, column=2).font = data_font
    ws.cell(row=r, column=2).alignment = center
    ws.cell(row=r, column=2).number_format = '#,##0'
    ws.cell(row=r, column=2).border = thin_border

    ws.cell(row=r, column=3, value=f'=COUNTIFS(Database!Q:Q,"{status}",Database!G:G,"P")')
    ws.cell(row=r, column=3).font = data_font
    ws.cell(row=r, column=3).alignment = center
    ws.cell(row=r, column=3).number_format = '#,##0'
    ws.cell(row=r, column=3).border = thin_border

    ws.cell(row=r, column=4, value=f"=B{r}+C{r}")
    ws.cell(row=r, column=4).font = data_bold_font
    ws.cell(row=r, column=4).alignment = center
    ws.cell(row=r, column=4).number_format = '#,##0'
    ws.cell(row=r, column=4).border = thin_border

    ws.cell(row=r, column=5, value=f'=IF(D${row_h+7}>0,D{r}/D${row_h+7},0)')
    ws.cell(row=r, column=5).font = pct_font
    ws.cell(row=r, column=5).alignment = center
    ws.cell(row=r, column=5).number_format = '0.0%'
    ws.cell(row=r, column=5).border = thin_border

    if idx % 2 == 0:
        for c in range(1, 6):
            ws.cell(row=r, column=c).fill = alt_fill

row_total_sk = row_h + 1 + len(status_list)
ws.cell(row=row_total_sk, column=1, value="TOTAL").font = data_bold_font
ws.cell(row=row_total_sk, column=1).fill = light_fill
ws.cell(row=row_total_sk, column=1).alignment = left_center
ws.cell(row=row_total_sk, column=1).border = thin_border

for c in range(2, 5):
    col_letter = get_column_letter(c)
    ws.cell(row=row_total_sk, column=c, value=f"=SUM({col_letter}{row_h+1}:{col_letter}{row_total_sk-1})")
    ws.cell(row=row_total_sk, column=c).font = data_bold_font
    ws.cell(row=row_total_sk, column=c).fill = light_fill
    ws.cell(row=row_total_sk, column=c).alignment = center
    ws.cell(row=row_total_sk, column=c).number_format = '#,##0'
    ws.cell(row=row_total_sk, column=c).border = thin_border

ws.cell(row=row_total_sk, column=5, value=f'=IF(D{row_total_sk}>0,D{row_total_sk}/D{row_total_sk},0)')
ws.cell(row=row_total_sk, column=5).font = data_bold_font
ws.cell(row=row_total_sk, column=5).fill = light_fill
ws.cell(row=row_total_sk, column=5).alignment = center
ws.cell(row=row_total_sk, column=5).number_format = '0.0%'
ws.cell(row=row_total_sk, column=5).border = thin_border

# === SECTION: Distribusi Jenjang Pendidikan (Right side) ===
jp_labels = ["SD", "SLTA/SMA Sederajat", "D-III", "D-IV", "S-1", "S-2", "S-3", "Profesi"]
jp_cols_start = 7

ws.merge_cells(f"G{row_s}:K{row_s}")
ws.cell(row=row_s, column=7, value="DISTRIBUSI BERDASARKAN JENJANG PENDIDIKAN").font = section_font
ws.cell(row=row_s, column=7).fill = section_fill
ws.cell(row=row_s, column=7).alignment = left_center
for c in range(7, 12):
    ws.cell(row=row_s, column=c).fill = section_fill

headers_jp = ["Jenjang Pendidikan", "Laki-laki", "Perempuan", "Total", "% of Total"]
for i, h in enumerate(headers_jp):
    cell = ws.cell(row=row_h, column=jp_cols_start+i, value=h)
    cell.font = header_font
    cell.fill = header_fill
    cell.alignment = center
    cell.border = thin_border

for idx, jp in enumerate(jp_labels):
    r = row_h + 1 + idx
    ws.cell(row=r, column=7, value=jp).font = data_font
    ws.cell(row=r, column=7).alignment = left_center
    ws.cell(row=r, column=7).border = thin_border

    ws.cell(row=r, column=8, value=f'=COUNTIFS(Database!M:M,"{jp}",Database!G:G,"L")')
    ws.cell(row=r, column=8).font = data_font
    ws.cell(row=r, column=8).alignment = center
    ws.cell(row=r, column=8).number_format = '#,##0'
    ws.cell(row=r, column=8).border = thin_border

    ws.cell(row=r, column=9, value=f'=COUNTIFS(Database!M:M,"{jp}",Database!G:G,"P")')
    ws.cell(row=r, column=9).font = data_font
    ws.cell(row=r, column=9).alignment = center
    ws.cell(row=r, column=9).number_format = '#,##0'
    ws.cell(row=r, column=9).border = thin_border

    ws.cell(row=r, column=10, value=f"=H{r}+I{r}")
    ws.cell(row=r, column=10).font = data_bold_font
    ws.cell(row=r, column=10).alignment = center
    ws.cell(row=r, column=10).number_format = '#,##0'
    ws.cell(row=r, column=10).border = thin_border

    total_row_jp = row_h + 1 + len(jp_labels)
    ws.cell(row=r, column=11, value=f'=IF(J${total_row_jp}>0,J{r}/J${total_row_jp},0)')
    ws.cell(row=r, column=11).font = pct_font
    ws.cell(row=r, column=11).alignment = center
    ws.cell(row=r, column=11).number_format = '0.0%'
    ws.cell(row=r, column=11).border = thin_border

    if idx % 2 == 0:
        for c in range(7, 12):
            ws.cell(row=r, column=c).fill = alt_fill

# Total row for JP
total_row_jp = row_h + 1 + len(jp_labels)
ws.cell(row=total_row_jp, column=7, value="TOTAL").font = data_bold_font
ws.cell(row=total_row_jp, column=7).fill = light_fill
ws.cell(row=total_row_jp, column=7).alignment = left_center
ws.cell(row=total_row_jp, column=7).border = thin_border

for c in range(8, 11):
    col_letter = get_column_letter(c)
    ws.cell(row=total_row_jp, column=c, value=f"=SUM({col_letter}{row_h+1}:{col_letter}{total_row_jp-1})")
    ws.cell(row=total_row_jp, column=c).font = data_bold_font
    ws.cell(row=total_row_jp, column=c).fill = light_fill
    ws.cell(row=total_row_jp, column=c).alignment = center
    ws.cell(row=total_row_jp, column=c).number_format = '#,##0'
    ws.cell(row=total_row_jp, column=c).border = thin_border

ws.cell(row=total_row_jp, column=11, value=1)
ws.cell(row=total_row_jp, column=11).font = data_bold_font
ws.cell(row=total_row_jp, column=11).fill = light_fill
ws.cell(row=total_row_jp, column=11).alignment = center
ws.cell(row=total_row_jp, column=11).number_format = '0.0%'
ws.cell(row=total_row_jp, column=11).border = thin_border

# === Blank row ===
blank_row = max(row_total_sk, total_row_jp) + 2
ws.row_dimensions[blank_row - 1].height = 10

# === SECTION: Distribusi Golongan (Left) ===
row_g = blank_row
golongan_list = ["I", "V", "VII", "IX", "X", "XII"]

ws.merge_cells(f"A{row_g}:E{row_g}")
ws.cell(row=row_g, column=1, value="DISTRIBUSI BERDASARKAN GOLONGAN").font = section_font
ws.cell(row=row_g, column=1).fill = section_fill
ws.cell(row=row_g, column=1).alignment = left_center
for c in range(1, 6):
    ws.cell(row=row_g, column=c).fill = section_fill
ws.row_dimensions[row_g].height = 30

row_gh = row_g + 1
headers_g = ["Golongan", "Laki-laki", "Perempuan", "Total", "% of Total"]
for i, h in enumerate(headers_g):
    cell = ws.cell(row=row_gh, column=i+1, value=h)
    cell.font = header_font
    cell.fill = header_fill
    cell.alignment = center
    cell.border = thin_border

for idx, gol in enumerate(golongan_list):
    r = row_gh + 1 + idx
    ws.cell(row=r, column=1, value=gol).font = data_font
    ws.cell(row=r, column=1).alignment = center
    ws.cell(row=r, column=1).border = thin_border

    ws.cell(row=r, column=2, value=f'=COUNTIFS(Database!L:L,"{gol}",Database!G:G,"L")')
    ws.cell(row=r, column=2).font = data_font
    ws.cell(row=r, column=2).alignment = center
    ws.cell(row=r, column=2).number_format = '#,##0'
    ws.cell(row=r, column=2).border = thin_border

    ws.cell(row=r, column=3, value=f'=COUNTIFS(Database!L:L,"{gol}",Database!G:G,"P")')
    ws.cell(row=r, column=3).font = data_font
    ws.cell(row=r, column=3).alignment = center
    ws.cell(row=r, column=3).number_format = '#,##0'
    ws.cell(row=r, column=3).border = thin_border

    ws.cell(row=r, column=4, value=f"=B{r}+C{r}")
    ws.cell(row=r, column=4).font = data_bold_font
    ws.cell(row=r, column=4).alignment = center
    ws.cell(row=r, column=4).number_format = '#,##0'
    ws.cell(row=r, column=4).border = thin_border

    total_row_g = row_gh + 1 + len(golongan_list)
    ws.cell(row=r, column=5, value=f'=IF(D${total_row_g}>0,D{r}/D${total_row_g},0)')
    ws.cell(row=r, column=5).font = pct_font
    ws.cell(row=r, column=5).alignment = center
    ws.cell(row=r, column=5).number_format = '0.0%'
    ws.cell(row=r, column=5).border = thin_border

    if idx % 2 == 0:
        for c in range(1, 6):
            ws.cell(row=r, column=c).fill = alt_fill

# Total row for Golongan
total_row_g = row_gh + 1 + len(golongan_list)
ws.cell(row=total_row_g, column=1, value="TOTAL").font = data_bold_font
ws.cell(row=total_row_g, column=1).fill = light_fill
ws.cell(row=total_row_g, column=1).alignment = left_center
ws.cell(row=total_row_g, column=1).border = thin_border

for c in range(2, 5):
    col_letter = get_column_letter(c)
    ws.cell(row=total_row_g, column=c, value=f"=SUM({col_letter}{row_gh+1}:{col_letter}{total_row_g-1})")
    ws.cell(row=total_row_g, column=c).font = data_bold_font
    ws.cell(row=total_row_g, column=c).fill = light_fill
    ws.cell(row=total_row_g, column=c).alignment = center
    ws.cell(row=total_row_g, column=c).number_format = '#,##0'
    ws.cell(row=total_row_g, column=c).border = thin_border

ws.cell(row=total_row_g, column=5, value=1)
ws.cell(row=total_row_g, column=5).font = data_bold_font
ws.cell(row=total_row_g, column=5).fill = light_fill
ws.cell(row=total_row_g, column=5).alignment = center
ws.cell(row=total_row_g, column=5).number_format = '0.0%'
ws.cell(row=total_row_g, column=5).border = thin_border

# === SECTION: Distribusi Golongan (Right side - repeated for chart data) ===
ws.merge_cells(f"G{row_g}:K{row_g}")
ws.cell(row=row_g, column=7, value="DISTRIBUSI BERDASARKAN STATUS KEPEGAWAIAN ( Grafik )").font = section_font
ws.cell(row=row_g, column=7).fill = section_fill
ws.cell(row=row_g, column=7).alignment = left_center
for c in range(7, 12):
    ws.cell(row=row_g, column=c).fill = section_fill

# We'll create chart data in columns G-K starting at row_gh
ws.cell(row=row_gh, column=7, value="Status Kepegawaian").font = header_font
ws.cell(row=row_gh, column=7).fill = header_fill
ws.cell(row=row_gh, column=7).alignment = center
ws.cell(row=row_gh, column=7).border = thin_border

ws.cell(row=row_gh, column=8, value="Laki-laki").font = header_font
ws.cell(row=row_gh, column=8).fill = header_fill
ws.cell(row=row_gh, column=8).alignment = center
ws.cell(row=row_gh, column=8).border = thin_border

ws.cell(row=row_gh, column=9, value="Perempuan").font = header_font
ws.cell(row=row_gh, column=9).fill = header_fill
ws.cell(row=row_gh, column=9).alignment = center
ws.cell(row=row_gh, column=9).border = thin_border

ws.cell(row=row_gh, column=10, value="Total").font = header_font
ws.cell(row=row_gh, column=10).fill = header_fill
ws.cell(row=row_gh, column=10).alignment = center
ws.cell(row=row_gh, column=10).border = thin_border

for idx, status in enumerate(status_list):
    r = row_gh + 1 + idx
    short_name = status.replace("P3K ", "")
    ws.cell(row=r, column=7, value=short_name).font = data_font
    ws.cell(row=r, column=7).alignment = left_center
    ws.cell(row=r, column=7).border = thin_border

    ws.cell(row=r, column=8, value=f'=COUNTIFS(Database!Q:Q,"{status}",Database!G:G,"L")')
    ws.cell(row=r, column=8).font = data_font
    ws.cell(row=r, column=8).alignment = center
    ws.cell(row=r, column=8).number_format = '#,##0'
    ws.cell(row=r, column=8).border = thin_border

    ws.cell(row=r, column=9, value=f'=COUNTIFS(Database!Q:Q,"{status}",Database!G:G,"P")')
    ws.cell(row=r, column=9).font = data_font
    ws.cell(row=r, column=9).alignment = center
    ws.cell(row=r, column=9).number_format = '#,##0'
    ws.cell(row=r, column=9).border = thin_border

    ws.cell(row=r, column=10, value=f"=H{r}+I{r}")
    ws.cell(row=r, column=10).font = data_bold_font
    ws.cell(row=r, column=10).alignment = center
    ws.cell(row=r, column=10).number_format = '#,##0'
    ws.cell(row=r, column=10).border = thin_border

    if idx % 2 == 0:
        for c in range(7, 11):
            ws.cell(row=r, column=c).fill = alt_fill

# === Blank row ===
blank_row2 = total_row_g + 2

# === SECTION: Distribusi Unit Kerja (Full Width) ===
row_uk = blank_row2
ws.merge_cells(f"A{row_uk}:K{row_uk}")
ws.cell(row=row_uk, column=1, value="DISTRIBUSI BERDASARKAN UNIT KERJA").font = section_font
ws.cell(row=row_uk, column=1).fill = section_fill
ws.cell(row=row_uk, column=1).alignment = left_center
for c in range(1, 12):
    ws.cell(row=row_uk, column=c).fill = section_fill
ws.row_dimensions[row_uk].height = 30

row_ukh = row_uk + 1
headers_uk = ["Unit Kerja", "Laki-laki", "Perempuan", "Total", "% of Total"]
for i, h in enumerate(headers_uk):
    cell = ws.cell(row=row_ukh, column=i+1, value=h)
    cell.font = header_font
    cell.fill = header_fill
    cell.alignment = center
    cell.border = thin_border

unit_kerja_list = [
    "Bpu", "Fakultas Ekonomi Dan Bisnis", "Fakultas Hukum",
    "Fakultas Kedokteran Dan Ilmu Kesehatan", "Fakultas Keguruan Dan Ilmu Pendidikan",
    "Fakultas Mipa", "Fakultas Pertanian", "Fakultas Peternakan",
    "Fakultas Teknik", "Fakultas Teknologi Pangan Dan Agroindustri",
    "Klinik", "Lpmpp", "Lppm", "Pascasarjana",
    "Rektorat ", "Rumah Sakit", "Upa. Bkpk", "Upa. Lab Terpadu",
    "Upa. Perpustakaan", "Upa. Bahasa", "Upa. Tik",
]

for idx, uk in enumerate(unit_kerja_list):
    r = row_ukh + 1 + idx
    ws.cell(row=r, column=1, value=uk).font = data_font
    ws.cell(row=r, column=1).alignment = left_center
    ws.cell(row=r, column=1).border = thin_border

    ws.cell(row=r, column=2, value=f'=COUNTIFS(Database!T:T,"{uk}",Database!G:G,"L")')
    ws.cell(row=r, column=2).font = data_font
    ws.cell(row=r, column=2).alignment = center
    ws.cell(row=r, column=2).number_format = '#,##0'
    ws.cell(row=r, column=2).border = thin_border

    ws.cell(row=r, column=3, value=f'=COUNTIFS(Database!T:T,"{uk}",Database!G:G,"P")')
    ws.cell(row=r, column=3).font = data_font
    ws.cell(row=r, column=3).alignment = center
    ws.cell(row=r, column=3).number_format = '#,##0'
    ws.cell(row=r, column=3).border = thin_border

    ws.cell(row=r, column=4, value=f"=B{r}+C{r}")
    ws.cell(row=r, column=4).font = data_bold_font
    ws.cell(row=r, column=4).alignment = center
    ws.cell(row=r, column=4).number_format = '#,##0'
    ws.cell(row=r, column=4).border = thin_border

    total_row_uk = row_ukh + 1 + len(unit_kerja_list)
    ws.cell(row=r, column=5, value=f'=IF(D${total_row_uk}>0,D{r}/D${total_row_uk},0)')
    ws.cell(row=r, column=5).font = pct_font
    ws.cell(row=r, column=5).alignment = center
    ws.cell(row=r, column=5).number_format = '0.0%'
    ws.cell(row=r, column=5).border = thin_border

    if idx % 2 == 0:
        for c in range(1, 6):
            ws.cell(row=r, column=c).fill = alt_fill

# Total row for Unit Kerja
total_row_uk = row_ukh + 1 + len(unit_kerja_list)
ws.cell(row=total_row_uk, column=1, value="TOTAL").font = data_bold_font
ws.cell(row=total_row_uk, column=1).fill = light_fill
ws.cell(row=total_row_uk, column=1).alignment = left_center
ws.cell(row=total_row_uk, column=1).border = thin_border

for c in range(2, 5):
    col_letter = get_column_letter(c)
    ws.cell(row=total_row_uk, column=c, value=f"=SUM({col_letter}{row_ukh+1}:{col_letter}{total_row_uk-1})")
    ws.cell(row=total_row_uk, column=c).font = data_bold_font
    ws.cell(row=total_row_uk, column=c).fill = light_fill
    ws.cell(row=total_row_uk, column=c).alignment = center
    ws.cell(row=total_row_uk, column=c).number_format = '#,##0'
    ws.cell(row=total_row_uk, column=c).border = thin_border

ws.cell(row=total_row_uk, column=5, value=1)
ws.cell(row=total_row_uk, column=5).font = data_bold_font
ws.cell(row=total_row_uk, column=5).fill = light_fill
ws.cell(row=total_row_uk, column=5).alignment = center
ws.cell(row=total_row_uk, column=5).number_format = '0.0%'
ws.cell(row=total_row_uk, column=5).border = thin_border

# === Blank ===
blank_row3 = total_row_uk + 2

# === SECTION: Log Data Summary ===
row_log = blank_row3
ws.merge_cells(f"A{row_log}:E{row_log}")
ws.cell(row=row_log, column=1, value="REKAP LOG DATA (Mutasi/Keluar)").font = section_font
ws.cell(row=row_log, column=1).fill = section_fill
ws.cell(row=row_log, column=1).alignment = left_center
for c in range(1, 6):
    ws.cell(row=row_log, column=c).fill = section_fill
ws.row_dimensions[row_log].height = 30

row_logh = row_log + 1
log_headers = ["Keterangan", "Jumlah"]
for i, h in enumerate(log_headers):
    cell = ws.cell(row=row_logh, column=i+1, value=h)
    cell.font = header_font
    cell.fill = header_fill
    cell.alignment = center
    cell.border = thin_border

log_types = ["MUTASI", "MENINGGAL", "MENGUNDURKAN DIRI"]
for idx, lt in enumerate(log_types):
    r = row_logh + 1 + idx
    ws.cell(row=r, column=1, value=lt).font = data_font
    ws.cell(row=r, column=1).alignment = left_center
    ws.cell(row=r, column=1).border = thin_border

    ws.cell(row=r, column=2, value=f'=COUNTIF(\'LOG DATA\'!L:L,"{lt}")')
    ws.cell(row=r, column=2).font = data_bold_font
    ws.cell(row=r, column=2).alignment = center
    ws.cell(row=r, column=2).number_format = '#,##0'
    ws.cell(row=r, column=2).border = thin_border

    if idx % 2 == 0:
        for c in range(1, 3):
            ws.cell(row=r, column=c).fill = alt_fill

total_log_row = row_logh + 1 + len(log_types)
ws.cell(row=total_log_row, column=1, value="TOTAL").font = data_bold_font
ws.cell(row=total_log_row, column=1).fill = light_fill
ws.cell(row=total_log_row, column=1).alignment = left_center
ws.cell(row=total_log_row, column=1).border = thin_border
ws.cell(row=total_log_row, column=2, value=f"=SUM(B{row_logh+1}:B{total_log_row-1})")
ws.cell(row=total_log_row, column=2).font = data_bold_font
ws.cell(row=total_log_row, column=2).fill = light_fill
ws.cell(row=total_log_row, column=2).alignment = center
ws.cell(row=total_log_row, column=2).number_format = '#,##0'
ws.cell(row=total_log_row, column=2).border = thin_border

# === SECTION: Log Data Detail ===
row_detail = total_log_row + 2
ws.merge_cells(f"A{row_detail}:E{row_detail}")
ws.cell(row=row_detail, column=1, value="DATA LOG (Mutasi Terakhir)").font = section_font
ws.cell(row=row_detail, column=1).fill = section_fill
ws.cell(row=row_detail, column=1).alignment = left_center
for c in range(1, 6):
    ws.cell(row=row_detail, column=c).fill = section_fill
ws.row_dimensions[row_detail].height = 30

row_dh = row_detail + 1
detail_headers = ["Nama", "Jabatan", "Asal Unit", "Tujuan Unit", "Keterangan"]
for i, h in enumerate(detail_headers):
    cell = ws.cell(row=row_dh, column=i+1, value=h)
    cell.font = header_font
    cell.fill = header_fill
    cell.alignment = center
    cell.border = thin_border

# Read LOG DATA sheet for detail
log_ws = wb["LOG DATA"]
log_data_rows = []
for row in log_ws.iter_rows(min_row=2, max_col=12, values_only=True):
    if row[0] is not None:
        log_data_rows.append(row)

# Show last 10 log entries (most recent)
max_display = min(10, len(log_data_rows))
for idx in range(max_display):
    r = row_dh + 1 + idx
    log_row = log_data_rows[-(max_display - idx)]

    ws.cell(row=r, column=1, value=log_row[3] if log_row[3] else "").font = data_font
    ws.cell(row=r, column=1).alignment = left_center
    ws.cell(row=r, column=1).border = thin_border

    ws.cell(row=r, column=2, value=log_row[4] if log_row[4] else "").font = data_font
    ws.cell(row=r, column=2).alignment = left_center
    ws.cell(row=r, column=2).border = thin_border

    ws.cell(row=r, column=3, value=log_row[6] if log_row[6] else "").font = data_font
    ws.cell(row=r, column=3).alignment = left_center
    ws.cell(row=r, column=3).border = thin_border

    ws.cell(row=r, column=4, value=log_row[7] if log_row[7] else "").font = data_font
    ws.cell(row=r, column=4).alignment = left_center
    ws.cell(row=r, column=4).border = thin_border

    ws.cell(row=r, column=5, value=log_row[11] if log_row[11] else "").font = data_font
    ws.cell(row=r, column=5).alignment = center
    ws.cell(row=r, column=5).border = thin_border

    if idx % 2 == 0:
        for c in range(1, 6):
            ws.cell(row=r, column=c).fill = alt_fill

# === CHARTS ===
chart_data_start_row = row_gh + 1
chart_data_end_row = row_gh + len(status_list)

# Chart 1: Bar Chart - Status Kepegawaian Distribution
chart1 = BarChart()
chart1.type = "col"
chart1.grouping = "stacked"
chart1.title = "Distribusi Status Kepegawaian"
chart1.y_axis.title = "Jumlah Pegawai"
chart1.x_axis.title = "Status Kepegawaian"
chart1.style = 10
chart1.width = 22
chart1.height = 14

data_ref_l = Reference(ws, min_col=8, min_row=row_gh, max_row=chart_data_end_row)
data_ref_p = Reference(ws, min_col=9, min_row=row_gh, max_row=chart_data_end_row)
cats = Reference(ws, min_col=7, min_row=chart_data_start_row, max_row=chart_data_end_row)

chart1.add_data(data_ref_l, titles_from_data=True)
chart1.add_data(data_ref_p, titles_from_data=True)
chart1.set_categories(cats)

chart1.series[0].graphicalProperties.solidFill = "2E75B6"
chart1.series[1].graphicalProperties.solidFill = "ED7D31"

chart1.legend.position = "b"

ws.add_chart(chart1, f"G{row_log}")

# Chart 2: Pie Chart - Gender Distribution
chart2 = PieChart()
chart2.title = "Distribusi Jenis Kelamin"
chart2.style = 10
chart2.width = 14
chart2.height = 14

# Use card values for pie chart
pie_data = Reference(ws, min_col=2, max_col=3, min_row=4, max_row=5)
pie_labels = Reference(ws, min_col=2, max_col=3, min_row=3, max_row=3)

chart2.add_data(pie_data, from_rows=True, titles_from_data=False)
chart2.set_categories(pie_labels)

# Try setting labels
chart2.dataLabels = DataLabelList()
chart2.dataLabels.showPercent = True
chart2.dataLabels.showVal = True
chart2.dataLabels.showCatName = True

if len(chart2.series) > 0:
    chart2.series[0].graphicalProperties.solidFill = "2E75B6"

ws.add_chart(chart2, f"G{blank_row3}")

# Chart 3: Bar Chart - Unit Kerja (horizontal) - placed to the right
chart3 = BarChart()
chart3.type = "bar"
chart3.grouping = "clustered"
chart3.title = "Distribusi Unit Kerja (Top)"
chart3.y_axis.title = "Unit Kerja"
chart3.x_axis.title = "Jumlah Pegawai"
chart3.style = 10
chart3.width = 22
chart3.height = 20

uk_data_l = Reference(ws, min_col=2, min_row=row_ukh, max_row=total_row_uk - 1)
uk_data_p = Reference(ws, min_col=3, min_row=row_ukh, max_row=total_row_uk - 1)
uk_cats = Reference(ws, min_col=1, min_row=row_ukh + 1, max_row=total_row_uk - 1)

chart3.add_data(uk_data_l, titles_from_data=True)
chart3.add_data(uk_data_p, titles_from_data=True)
chart3.set_categories(uk_cats)

chart3.series[0].graphicalProperties.solidFill = "2E75B6"
chart3.series[1].graphicalProperties.solidFill = "ED7D31"
chart3.legend.position = "b"

ws.add_chart(chart3, f"G{total_row_uk + 1}")

# === Print setup ===
ws.sheet_properties.pageSetUpPr = openpyxl.worksheet.properties.PageSetupProperties(fitToPage=True)
ws.page_setup.fitToWidth = 1
ws.page_setup.fitToHeight = 0
ws.page_setup.orientation = "landscape"

# === Freeze panes ===
ws.freeze_panes = "A3"

# Save
wb.save(r"C:\Users\User\Documents\PPPK.xlsx")
print("Dashboard Analisa berhasil dibuat!")
print(f"  - Sheet: Dashboard Analisa")
print(f"  - Summary Cards (6 metric)")
print(f"  - Tabel Distribusi Status Kepegawaian (6 jenis)")
print(f"  - Tabel Distribusi Jenjang Pendidikan (8 jenjang)")
print(f"  - Tabel Distribusi Golongan (6 golongan)")
print(f"  - Tabel Distribusi Unit Kerja (21 unit)")
print(f"  - Rekap Log Data (Mutasi/Meninggal/Mengundurkan Diri)")
print(f"  - Detail Log Data (10 entri terakhir)")
print(f"  - 3 Chart (Bar Status Kepegawaian, Pie Gender, Bar Unit Kerja)")
