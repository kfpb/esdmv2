/**
 * Utility Export PDF Standar Korporat PT Kimia Farma Tbk - Plant Banjaran
 * Menghasilkan dokumen cetak / PDF vektor siap simpan dengan header resmi,
 * metadata filter aktif, tabel terstruktur, dan blok tanda tangan pengesahan.
 * Mendukung ukuran kertas A4 / F4 (Folio) Portrait dan Landscape.
 */

export function exportTableToPdf({
    title = 'REKAPITULASI DATA',
    subtitle = 'PT KIMIA FARMA TBK - PLANT BANJARAN',
    documentNo = '',
    metaInfo = [],
    legendItems = [],
    columns = [],
    data = [],
    paperSize = 'A4',
    orientation = 'portrait',
}) {
    if (!data || data.length === 0) {
        alert('Tidak ada data yang dapat diekspor.');
        return;
    }

    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        alert('Pop-up terblokir oleh browser. Harap izinkan pop-up untuk mengunduh / mencetak PDF.');
        return;
    }

    const currentDate = new Date().toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
    const currentDateTime = new Date().toLocaleDateString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

    const isF4 = paperSize.toUpperCase() === 'F4' || paperSize.toUpperCase() === 'FOLIO';
    const isPortrait = orientation === 'portrait';

    const metaHtml = metaInfo
        .map(
            (m) => `
        <div style="display: flex; gap: 6px; font-size: 10px; color: #1e293b;">
            <strong style="color: #475569; min-width: 82px;">${m.label}:</strong>
            <span style="font-weight: 600;">${m.value}</span>
        </div>
    `
        )
        .join('');

    const headersHtml = columns
        .map(
            (col) => `
        <th style="
            padding: 6px 7px;
            background-color: #f1f5f9;
            color: #000000;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            text-align: ${col.align || 'left'};
            border-top: 1.5px solid #000000;
            border-bottom: 2px solid #000000;
            border-left: 1px solid #cbd5e1;
            border-right: 1px solid #cbd5e1;
            ${col.width ? `width: ${col.width};` : ''}
        ">
            ${col.header}
        </th>
    `
        )
        .join('');

    const legendHtml = legendItems && legendItems.length > 0
        ? `
        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; font-size: 9px; color: #334155; padding: 5px 10px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px;">
            <strong style="color: #0f172a; margin-right: 4px;">KETERANGAN STATUS:</strong>
            ${legendItems
                .map(
                    (l) => `
                <div style="display: flex; align-items: center; gap: 4px;">
                    <span style="display: inline-block; width: 12px; height: 9px; background-color: ${l.color}; border: 1px solid ${l.border || '#cbd5e1'}; border-radius: 2px;"></span>
                    <span style="font-weight: 500;">${l.label}</span>
                </div>
            `
                )
                .join('')}
        </div>
    `
        : '';

    const rowsHtml = data
        .map((row, index) => {
            let bg = index % 2 === 0 ? '#ffffff' : '#f8fafc';
            let textCol = '#1e293b';

            if (row.badge_type === 'weekend') {
                bg = '#fee2e2'; // merah muda lembut
            } else if (row.badge_type === 'libur_nasional') {
                bg = '#fef3c7'; // kuning peach lembut
            } else if (row.badge_type === 'lembur_libur') {
                bg = '#dcfce7'; // hijau muda lembut
            } else if (row.badge_type === 'alpha') {
                bg = '#e2e8f0'; // abu-abu lembut
            } else if (row.badge_type === 'belum_berjalan') {
                bg = '#ffffff';
                textCol = '#64748b'; // abu-abu halus untuk baris belum berjalan
            }

            const cells = columns
                .map((col) => {
                    let val = '';
                    if (typeof col.render === 'function') {
                        val = col.render(row, index);
                    } else if (col.field) {
                        val = row[col.field] ?? '-';
                    }
                    return `
                    <td style="
                        padding: 5px 6.5px;
                        font-size: 9px;
                        color: ${textCol};
                        border: 1px solid #cbd5e1;
                        text-align: ${col.align || 'left'};
                        vertical-align: middle;
                        word-break: break-word;
                        ${col.width ? `width: ${col.width};` : ''}
                    ">
                        ${val}
                    </td>
                `;
                })
                .join('');

            return `<tr style="background-color: ${bg};">${cells}</tr>`;
        })
        .join('');

    const htmlContent = `
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${title} - ${subtitle}</title>
    <style id="page-size-style">
        @page {
            size: ${isF4 ? '215mm 330mm' : 'A4'} ${orientation};
            margin: 8mm 8mm 10mm 8mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: Arial, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 16px;
            background-color: #f1f5f9;
            color: #1e293b;
        }
        .container {
            max-width: ${isPortrait ? '750px' : '100%'};
            margin: 0 auto;
            background: #ffffff;
            padding: 18px 22px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border-bottom: 3px double #000000;
            padding-bottom: 8px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .meta-box {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 4px 14px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 10px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            border: 1px solid #94a3b8;
        }
        .signature-table {
            width: 100%;
            margin-top: 22px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .signature-table td {
            text-align: center;
            font-size: 10px;
            color: #334155;
            vertical-align: top;
            width: 33.33%;
        }
        .no-print-toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 8px 14px;
            border-radius: 8px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            max-width: ${isPortrait ? '750px' : '100%'};
            margin-left: auto;
            margin-right: auto;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: background 0.15s;
        }
        .btn-primary {
            background-color: #005BAC;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #004785;
        }
        .btn-secondary {
            background-color: #e2e8f0;
            color: #334155;
        }
        .btn-secondary:hover {
            background-color: #cbd5e1;
        }
        @media print {
            body {
                background-color: #ffffff;
                padding: 0;
            }
            .container {
                max-width: 100% !important;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
            .no-print-toolbar {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print-toolbar">
        <div style="font-size: 11.5px; color: #475569; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span style="font-weight: 700; color: #000000;">Pratinjau Cetak / Ekspor PDF Resmi KFPB</span>
            <div style="display: flex; align-items: center; gap: 6px;">
                <label for="paperSelect" style="font-size: 11px; font-weight: 600; color: #334155;">Ukuran Kertas:</label>
                <select id="paperSelect" onchange="changePaperSize(this.value)" style="padding: 3px 8px; border-radius: 4px; border: 1px solid #94a3b8; font-size: 11px; background: #ffffff; color: #0f172a; cursor: pointer;">
                    <option value="A4" ${!isF4 ? 'selected' : ''}>A4 (${isPortrait ? '210 x 297 mm' : '297 x 210 mm'})</option>
                    <option value="F4" ${isF4 ? 'selected' : ''}>F4 / Folio (${isPortrait ? '215 x 330 mm' : '330 x 215 mm'})</option>
                </select>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <button class="btn btn-primary" onclick="window.print()">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-12 0h12v4H6v-4z"></path></svg>
                Cetak / Simpan PDF
            </button>
            <button class="btn btn-secondary" onclick="window.close()">Tutup</button>
        </div>
    </div>

    <div class="container">
        <!-- Official Corporate Letterhead Header -->
        <table class="header-table">
            <tr>
                <td style="width: 60px; text-align: left;">
                    <div style="width: 44px; height: 44px; border-radius: 6px; background-color: #000000; display: flex; align-items: center; justify-content: center; color: #ffffff; font-weight: bold; font-size: 18px;">
                        KF
                    </div>
                </td>
                <td>
                    <div style="font-size: 15px; font-weight: 800; color: #000000; letter-spacing: 0.5px; text-transform: uppercase;">
                        PT KIMIA FARMA TBK &bull; PLANT BANJARAN
                    </div>
                    <div style="font-size: 10px; color: #000000; margin-top: 1px;">
                        Jl. Raya Banjaran KM 29.5, Banjaran, Kab. Bandung &bull; Sistem Elektronik Sumber Daya Manusia (eSDM)
                    </div>
                </td>
                <td style="text-align: right; font-size: 9.5px; color: #000000;">
                    <div><strong>KODE DOKUMEN:</strong> ${documentNo || 'KFPB-SDM-REC'}</div>
                    <div style="margin-top: 2px;">Dicetak: ${currentDateTime} WIB</div>
                </td>
            </tr>
        </table>

        <!-- Document Title -->
        <div style="text-align: center; margin: 10px 0 12px 0;">
            <h2 style="margin: 0; font-size: 14px; font-weight: 800; color: #000000; text-transform: uppercase; letter-spacing: 0.5px;">
                ${title}
            </h2>
            <div style="font-size: 10px; color: #000000; margin-top: 2px;">
                ${subtitle}
            </div>
        </div>

        <!-- Filter / Meta Information Box -->
        <div class="meta-box">
            ${metaHtml}
            <div style="display: flex; gap: 6px; font-size: 10px; color: #1e293b;">
                <strong style="color: #475569; min-width: 82px;">Total Record:</strong>
                <span style="font-weight: 700; color: #0f172a;">${data.length} Hari</span>
            </div>
        </div>

        <!-- Visual Status Legend -->
        ${legendHtml}

        <!-- Main Data Table -->
        <table class="data-table">
            <thead>
                <tr>
                    ${headersHtml}
                </tr>
            </thead>
            <tbody>
                ${rowsHtml}
            </tbody>
        </table>

        <!-- Official Signatures / Pengesahan -->
        <table class="signature-table">
            <tr>
                <td>
                    <div>Diajukan / Diperiksa oleh,</div>
                    <div style="font-weight: 600; margin-top: 3px;">Pegawai Yang Bersangkutan</div>
                    <div style="height: 50px;"></div>
                    <div style="text-decoration: underline; font-weight: 700; color: #0f172a;">
                        ${metaInfo.find(m => m.label.toLowerCase().includes('nama'))?.value?.split('(')[0]?.trim() || '...........................................'}
                    </div>
                    <div style="font-size: 9px; color: #64748b; margin-top: 2px;">NIP: ${metaInfo.find(m => m.label.toLowerCase().includes('nama'))?.value?.match(/\(([^)]+)\)/)?.[1] || '....................'}</div>
                </td>
                <td>
                    <div>Mengetahui,</div>
                    <div style="font-weight: 600; margin-top: 3px;">Atasan Langsung</div>
                    <div style="height: 50px;"></div>
                    <div style="text-decoration: underline; font-weight: 700; color: #0f172a;">...................................................</div>
                    <div style="font-size: 9px; color: #64748b; margin-top: 2px;">Supervisor / Asman</div>
                </td>
                <td>
                    <div>Banjaran, ${currentDate}</div>
                    <div style="font-weight: 600; margin-top: 3px;">Bagian SDM &amp; Umum</div>
                    <div style="height: 50px;"></div>
                    <div style="text-decoration: underline; font-weight: 700; color: #0f172a;">...................................................</div>
                    <div style="font-size: 9px; color: #64748b; margin-top: 2px;">Human Capital Plant Banjaran</div>
                </td>
            </tr>
        </table>
    </div>

    <script>
        function changePaperSize(size) {
            var pageStyle = document.getElementById('page-size-style');
            var isFolio = (size === 'F4' || size === 'FOLIO');
            var pageSize = isFolio ? '215mm 330mm' : 'A4';
            pageStyle.innerHTML = '@page { size: ' + pageSize + ' ${orientation}; margin: 8mm 8mm 10mm 8mm; }';
        }

        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 350);
        });
    </script>
</body>
</html>
    `;

    printWindow.document.open();
    printWindow.document.write(htmlContent);
    printWindow.document.close();
}
