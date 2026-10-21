import { StockMovement } from '../../models/stock.model';

const COLUMNS = [
  'Date',
  'Produit',
  'Site',
  'Type',
  'Quantité',
  'Stock avant',
  'Stock après',
  'Utilisateur',
];

export function movementRows(
  items: StockMovement[],
  labelType: (type: string) => string,
  formatDate: (value: string) => string
): string[][] {
  return items.map((item) => [
    formatDate(item.createdAt || ''),
    item.productName || `Produit #${item.productId}`,
    item.siteName || `Site #${item.siteId}`,
    labelType(item.type),
    String(item.quantity),
    String(item.stockBefore),
    String(item.stockAfter),
    item.userFullName || `Utilisateur #${item.userId}`,
  ]);
}

export function downloadExcel(title: string, rows: string[][]): void {
  const lines = [COLUMNS, ...rows].map((row) => row.map(csvCell).join(';'));
  const blob = new Blob(['\uFEFF' + lines.join('\r\n')], {
    type: 'text/csv;charset=utf-8',
  });
  saveBlob(blob, `${fileName(title)}.csv`);
}

export function downloadPdf(title: string, rows: string[][]): void {
  const blob = new Blob([buildPdf(title, rows)], { type: 'application/pdf' });
  saveBlob(blob, `${fileName(title)}.pdf`);
}

function csvCell(value: string): string {
  return `"${value.replace(/"/g, '""')}"`;
}

function fileName(title: string): string {
  const day = new Date().toISOString().slice(0, 10);
  const slug = title
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
  return `${slug || 'historique'}-${day}`;
}

function saveBlob(blob: Blob, name: string): void {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  link.click();
  URL.revokeObjectURL(url);
}

function buildPdf(title: string, rows: string[][]): Uint8Array {
  const pageWidth = 842;
  const pageHeight = 595;
  const margin = 36;
  const rowHeight = 16;
  const headerHeight = 18;
  const columns = [118, 150, 130, 78, 62, 70, 70, 92];
  const pages: string[][][] = [];
  let cursor = 0;
  const firstCapacity = 28;
  const nextCapacity = 28;

  while (cursor < rows.length || pages.length === 0) {
    const capacity = pages.length === 0 ? firstCapacity : nextCapacity;
    pages.push(rows.slice(cursor, cursor + capacity));
    cursor += capacity;
    if (cursor >= rows.length) break;
  }

  const objects: string[] = [];
  const pageIds: number[] = [];
  const fontId = 3;
  let nextId = 4;

  pages.forEach((pageRows, index) => {
    const pageId = nextId++;
    const contentId = nextId++;
    pageIds.push(pageId);
    const startY = 510;
    const commands = [
      'BT',
      '/F1 16 Tf',
      `1 0 0 1 ${margin} ${pageHeight - 40} Tm`,
      `(${pdfText(title)}) Tj`,
      '/F1 9 Tf',
      `1 0 0 1 ${margin} ${pageHeight - 56} Tm`,
      `(${pdfText(`Page ${index + 1} / ${pages.length} · ${rows.length} ligne(s)`)}) Tj`,
      'ET',
    ];
    let y = startY;
    commands.push(...rowCommands(COLUMNS, columns, margin, y, headerHeight, true));
    y -= headerHeight;
    pageRows.forEach((row) => {
      commands.push(...rowCommands(row, columns, margin, y, rowHeight, false));
      y -= rowHeight;
    });
    objects[contentId] = streamObject(commands.join('\n'));
    objects[pageId] =
      `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${pageWidth} ${pageHeight}] ` +
      `/Contents ${contentId} 0 R /Resources << /Font << /F1 ${fontId} 0 R >> >> >>`;
  });

  objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
  objects[2] =
    `<< /Type /Pages /Count ${pageIds.length} /Kids [${pageIds.map((id) => `${id} 0 R`).join(' ')}] >>`;
  objects[fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

  let pdf = '%PDF-1.4\n';
  const offsets = [0];
  for (let id = 1; id < objects.length; id++) {
    if (!objects[id]) continue;
    offsets[id] = pdf.length;
    pdf += `${id} 0 obj\n${objects[id]}\nendobj\n`;
  }
  const xref = pdf.length;
  let body = `xref\n0 ${objects.length}\n`;
  body += '0000000000 65535 f \n';
  for (let id = 1; id < objects.length; id++) {
    body += objects[id] ? `${String(offsets[id]).padStart(10, '0')} 00000 n \n` : '0000000000 65535 f \n';
  }
  body += `trailer << /Size ${objects.length} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;
  return new TextEncoder().encode(pdf + body);
}

function rowCommands(
  cells: string[],
  widths: number[],
  x: number,
  y: number,
  height: number,
  header: boolean
): string[] {
  const commands = [`0.93 0.95 0.97 rg`, `${x} ${y - 4} ${widths.reduce((sum, width) => sum + width, 0)} ${height} re f`];
  if (header) commands[0] = '0.94 0.96 0.98 rg';
  commands.push('0.12 0.16 0.22 rg', 'BT', '/F1 8 Tf');
  let cursor = x + 4;
  cells.forEach((cell, index) => {
    const max = Math.max(4, Math.floor((widths[index] - 8) / 4.4));
    commands.push(`1 0 0 1 ${cursor} ${y} Tm`, `(${pdfText(clip(cell, max))}) Tj`);
    cursor += widths[index];
  });
  commands.push('ET');
  return commands;
}

function streamObject(content: string): string {
  const length = new TextEncoder().encode(content).length;
  return `<< /Length ${length} >>\nstream\n${content}\nendstream`;
}

function clip(value: string, max: number): string {
  return value.length > max ? `${value.slice(0, max - 1)}…` : value;
}

function pdfText(value: string): string {
  const extra: Record<string, number> = {
    'é': 0xe9, 'è': 0xe8, 'ê': 0xea, 'ë': 0xeb,
    'à': 0xe0, 'â': 0xe2, 'ù': 0xf9, 'û': 0xfb,
    'ô': 0xf4, 'î': 0xee, 'ï': 0xef, 'ç': 0xe7,
    'É': 0xc9, 'È': 0xc8, 'À': 0xc0, 'Ç': 0xc7,
    '’': 0x92, '…': 0x85, '·': 0xb7,
  };
  let out = '';
  for (const char of value.replace(/[\u202f\u00a0]/g, ' ')) {
    const code = char.codePointAt(0) ?? 63;
    if (char === '\\' || char === '(' || char === ')') out += `\\${char}`;
    else if (code >= 32 && code <= 126) out += char;
    else if (extra[char] != null) out += `\\${extra[char].toString(8).padStart(3, '0')}`;
    else out += '?';
  }
  return out;
}
