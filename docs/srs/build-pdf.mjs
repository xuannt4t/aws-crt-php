// Render docs/srs/SRS.md -> docs/srs/DORMIDA-WORK-SRS.pdf
//
// Không phụ thuộc package ngoài: tự chuyển Markdown (tập con dùng trong SRS.md)
// sang HTML rồi in bằng Chrome/Edge headless.
//
//   node docs/srs/build-pdf.mjs
//
// Chỉ định trình duyệt khác qua biến môi trường CHROME_PATH nếu cần.

import { execFileSync } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const SOURCE = join(HERE, 'SRS.md');
const OUTPUT = join(HERE, 'DORMIDA-WORK-SRS.pdf');

/* ---------------------------------------------------------------- markdown */

const escapeHtml = (text) =>
    text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const CODE_MARK = '\u0000';
const CODE_PATTERN = new RegExp(CODE_MARK + '([0-9]+)' + CODE_MARK, 'g');

function inline(text) {
    // Tách code span ra trước để `**`, `*` bên trong không bị hiểu là định dạng.
    const codeSpans = [];
    const withPlaceholders = escapeHtml(text).replace(/`([^`]+)`/g, (_, code) => {
        codeSpans.push(code);
        return `${CODE_MARK}${codeSpans.length - 1}${CODE_MARK}`;
    });

    return withPlaceholders
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>')
        .replace(CODE_PATTERN, (_, index) => `<code>${codeSpans[index]}</code>`);
}

const slug = (text) =>
    text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

const splitRow = (line) =>
    line
        .replace(/^\||\|$/g, '')
        .split('|')
        .map((cell) => cell.trim());

const isTableDivider = (line) => /^\|[\s:|-]+\|$/.test(line.trim());

function renderMarkdown(markdown) {
    const lines = markdown.replace(/\r\n/g, '\n').split('\n');
    const html = [];
    const headings = [];
    let i = 0;

    while (i < lines.length) {
        const line = lines[i];

        if (!line.trim()) {
            i += 1;
            continue;
        }

        // Fenced code block
        if (line.startsWith('```')) {
            const body = [];
            i += 1;
            while (i < lines.length && !lines[i].startsWith('```')) {
                body.push(lines[i]);
                i += 1;
            }
            i += 1;
            html.push(`<pre><code>${escapeHtml(body.join('\n'))}</code></pre>`);
            continue;
        }

        // Horizontal rule
        if (/^---+$/.test(line.trim())) {
            html.push('<hr>');
            i += 1;
            continue;
        }

        // Heading
        const heading = line.match(/^(#{1,4})\s+(.*)$/);
        if (heading) {
            const level = heading[1].length;
            const text = heading[2].trim();
            const id = slug(text);
            if (level === 2 || level === 3) {
                headings.push({ level, text, id });
            }
            html.push(`<h${level} id="${id}">${inline(text)}</h${level}>`);
            i += 1;
            continue;
        }

        // Table
        if (line.trim().startsWith('|') && isTableDivider(lines[i + 1] ?? '')) {
            const header = splitRow(line);
            i += 2;
            const rows = [];
            while (i < lines.length && lines[i].trim().startsWith('|')) {
                rows.push(splitRow(lines[i]));
                i += 1;
            }
            const head = header.map((cell) => `<th>${inline(cell)}</th>`).join('');
            const body = rows
                .map((row) => `<tr>${row.map((cell) => `<td>${inline(cell)}</td>`).join('')}</tr>`)
                .join('');
            const headless = header.every((cell) => cell === '');
            html.push(
                `<table>${headless ? '' : `<thead><tr>${head}</tr></thead>`}<tbody>${body}</tbody></table>`
            );
            continue;
        }

        // List (ordered / unordered)
        const bullet = line.match(/^(\s*)([-*]|\d+\.)\s+/);
        if (bullet) {
            const ordered = /\d/.test(bullet[2]);
            const items = [];
            while (i < lines.length) {
                const item = lines[i].match(/^(\s*)(?:[-*]|\d+\.)\s+(.*)$/);
                if (!item) break;
                items.push(item[2]);
                i += 1;
            }
            const body = items.map((item) => `<li>${inline(item)}</li>`).join('');
            html.push(ordered ? `<ol>${body}</ol>` : `<ul>${body}</ul>`);
            continue;
        }

        // Paragraph
        const paragraph = [];
        while (i < lines.length && lines[i].trim() && !/^(#{1,4}\s|```|\||---+$)/.test(lines[i])) {
            paragraph.push(lines[i].trim());
            i += 1;
        }
        html.push(`<p>${inline(paragraph.join(' '))}</p>`);
    }

    return { body: html.join('\n'), headings };
}

function renderToc(headings) {
    const items = headings
        .filter((heading) => heading.level === 2)
        .map((heading) => `<li><a href="#${heading.id}">${inline(heading.text)}</a></li>`)
        .join('');

    // Heading trong SRS.md đã tự đánh số nên mục lục không đánh số lần nữa.
    return `<nav class="toc"><h2 class="toc-title">Mục lục</h2><ul>${items}</ul></nav>`;
}

const STYLE = `
  @page { size: A4; margin: 18mm 16mm; }
  * { box-sizing: border-box; }
  body {
    font-family: "Segoe UI", "Times New Roman", serif;
    font-size: 10.5pt; line-height: 1.55; color: #1a1a1a; margin: 0;
  }
  h1, h2, h3, h4 { font-family: "Segoe UI", Arial, sans-serif; color: #0f2b46; line-height: 1.3; }
  h1 { font-size: 24pt; margin: 0 0 6mm; }
  h2 { font-size: 15pt; margin: 9mm 0 3mm; padding-bottom: 1.5mm; border-bottom: 1.5pt solid #0f2b46;
       page-break-after: avoid; }
  h3 { font-size: 12pt; margin: 6mm 0 2mm; page-break-after: avoid; }
  h4 { font-size: 10.5pt; margin: 4mm 0 2mm; page-break-after: avoid; }
  p { margin: 0 0 3mm; text-align: justify; }
  ul, ol { margin: 0 0 3mm; padding-left: 6mm; }
  li { margin-bottom: 1mm; }
  hr { border: none; border-top: 0.5pt solid #d0d7de; margin: 6mm 0; }
  a { color: #0f2b46; text-decoration: none; }
  code { font-family: Consolas, "Courier New", monospace; font-size: 9pt;
         background: #f2f4f7; padding: 0.3mm 1mm; border-radius: 2px; }
  pre { background: #f7f8fa; border: 0.5pt solid #d0d7de; border-left: 2pt solid #0f2b46;
        padding: 3mm 4mm; margin: 0 0 4mm; overflow: hidden; page-break-inside: avoid; }
  pre code { background: none; padding: 0; font-size: 9pt; line-height: 1.45; }
  table { width: 100%; border-collapse: collapse; margin: 0 0 4mm; font-size: 9.5pt;
          page-break-inside: auto; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  th, td { border: 0.5pt solid #c9d1d9; padding: 1.6mm 2.2mm; text-align: left; vertical-align: top; }
  th { background: #0f2b46; color: #fff; font-weight: 600; }
  tbody tr:nth-child(even) { background: #f6f8fa; }

  .cover { page-break-after: always; padding-top: 45mm; }
  .cover h1 { font-size: 30pt; border: none; }
  .cover .subtitle { font-size: 13pt; color: #4a5b6b; margin-bottom: 14mm; }
  .cover table { font-size: 10.5pt; }
  .cover th { display: none; }
  .cover td:first-child { width: 42%; background: #f6f8fa; font-weight: 600; }

  .toc { page-break-after: always; }
  .toc-title { border: none; }
  .toc ul { padding-left: 0; list-style: none; }
  .toc li { margin-bottom: 2.5mm; font-size: 11pt; border-bottom: 0.5pt dotted #c9d1d9;
            padding-bottom: 1mm; }
`;

/* ------------------------------------------------------------------- build */

function findBrowser() {
    const candidates = [
        process.env.CHROME_PATH,
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
    ].filter(Boolean);

    const found = candidates.find((path) => existsSync(path));
    if (!found) {
        throw new Error(
            'Không tìm thấy Chrome/Edge. Đặt biến môi trường CHROME_PATH trỏ tới trình duyệt.'
        );
    }
    return found;
}

const markdown = readFileSync(SOURCE, 'utf8');
const { body, headings } = renderMarkdown(markdown);

// Phần trước dấu `---` đầu tiên là trang bìa.
const separator = body.indexOf('<hr>');
const coverHtml = body.slice(0, separator);
const restHtml = body.slice(separator + '<hr>'.length);

const document = `<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>SRS — DORMIDA WORK</title>
<style>${STYLE}</style>
</head>
<body>
<section class="cover">${coverHtml}</section>
${renderToc(headings)}
${restHtml}
</body>
</html>`;

// `--keep-html` giữ lại bản HTML trung gian trong docs/srs để soi lại style.
const keepHtml = process.argv.includes('--keep-html');
const workDir = mkdtempSync(join(tmpdir(), 'srs-pdf-'));
const htmlPath = keepHtml ? join(HERE, 'srs.preview.html') : join(workDir, 'srs.html');
writeFileSync(htmlPath, document, 'utf8');

try {
    execFileSync(
        findBrowser(),
        [
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--no-pdf-header-footer',
            `--user-data-dir=${join(workDir, 'profile')}`,
            `--print-to-pdf=${OUTPUT}`,
            pathToFileURL(htmlPath).href,
        ],
        { stdio: 'pipe' }
    );
} finally {
    rmSync(workDir, { recursive: true, force: true });
}

if (!existsSync(OUTPUT)) {
    throw new Error('Trình duyệt không tạo được file PDF.');
}

console.log(`PDF: ${resolve(OUTPUT)}`);
