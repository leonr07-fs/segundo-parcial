import fs from 'node:fs/promises';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const root = process.cwd();
const outputDir = path.join(root, 'storage', 'app', 'datos_sinteticos');
const buildDir = path.join(outputDir, 'xlsx_build');
const outputXlsx = path.join(outputDir, 'datos_sinteticos_cup_3000.xlsx');
const outputZip = path.join(outputDir, 'datos_sinteticos_cup_3000.zip');

const TOTAL_POSTULANTES = 3000;
const TOTAL_DOCENTES = 60;
const GESTION = 'Semestre 1 2026';
const ANIO = 2026;
const materias = ['Matematica', 'Computacion', 'Ingles', 'Fisica'];
const carreras = ['Ingenieria en Sistemas', 'Informatica', 'Redes', 'Robotica'];
const ciudades = ['Santa Cruz', 'Warnes', 'Montero', 'La Guardia', 'Cotoca'];
const colegios = ['Carlos Laborde Pulido', 'Nacional Florida', 'Uboldi', 'La Salle', 'Don Bosco', 'San Agustin'];
const nombresH = ['Carlos', 'Juan', 'Miguel', 'Luis', 'Jose', 'Andres', 'Diego', 'Fernando', 'Raul', 'Marco'];
const nombresM = ['Marilyn', 'Ana', 'Maria', 'Lucia', 'Gabriela', 'Paola', 'Daniela', 'Carla', 'Sofia', 'Roxana'];
const apellidos = ['Condori', 'Diaz', 'Rojas', 'Vargas', 'Mamani', 'Flores', 'Suarez', 'Gomez', 'Arce', 'Mendez', 'Lopez', 'Quiroga'];

function pick(items, index) {
  return items[index % items.length];
}

function pad(number, size) {
  return String(number).padStart(size, '0');
}

function slug(value) {
  return value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '.').replace(/^\.+|\.+$/g, '');
}

function notaFor(i, materiaIndex, examen) {
  const seed = (i * 17 + materiaIndex * 13 + examen * 7) % 101;
  if ((i + materiaIndex) % 11 === 0 && examen === 1) return 45 + (seed % 10);
  if ((i + materiaIndex) % 17 === 0 && examen === 2) return 50 + (seed % 9);
  return 60 + (seed % 36);
}

function estadoNotas(ex1, ex2, ex3) {
  if (ex1 < 60 || (ex2 !== '' && ex2 < 60) || (ex3 !== '' && ex3 < 60)) return 'reprobado';
  return 'aprobado';
}

function promedioNotas(ex1, ex2, ex3) {
  const notas = [ex1, ex2, ex3].filter((value) => value !== '');
  return Math.round((notas.reduce((sum, value) => sum + value, 0) / notas.length) * 100) / 100;
}

function cellRef(colIndex, rowIndex) {
  let col = '';
  let n = colIndex;
  while (n > 0) {
    const mod = (n - 1) % 26;
    col = String.fromCharCode(65 + mod) + col;
    n = Math.floor((n - mod) / 26);
  }
  return `${col}${rowIndex}`;
}

function escapeXml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&apos;');
}

function xmlCell(value, colIndex, rowIndex) {
  const ref = cellRef(colIndex, rowIndex);
  if (typeof value === 'number') {
    return `<c r="${ref}"><v>${value}</v></c>`;
  }
  return `<c r="${ref}" t="inlineStr"><is><t>${escapeXml(value)}</t></is></c>`;
}

function sheetXml(headers, rows) {
  const xmlRows = [];
  xmlRows.push(`<row r="1">${headers.map((header, index) => xmlCell(header, index + 1, 1)).join('')}</row>`);
  rows.forEach((row, rowIndex) => {
    const excelRow = rowIndex + 2;
    xmlRows.push(`<row r="${excelRow}">${row.map((value, colIndex) => xmlCell(value, colIndex + 1, excelRow)).join('')}</row>`);
  });
  const colCount = headers.length;
  const rowCount = rows.length + 1;
  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <dimension ref="A1:${cellRef(colCount, rowCount)}"/>
  <sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>
  <cols>${headers.map((_, index) => `<col min="${index + 1}" max="${index + 1}" width="20" customWidth="1"/>`).join('')}</cols>
  <sheetData>${xmlRows.join('')}</sheetData>
  <autoFilter ref="A1:${cellRef(colCount, rowCount)}"/>
</worksheet>`;
}

function buildData() {
  const docentes = [];
  for (let i = 1; i <= TOTAL_DOCENTES; i++) {
    const nombre = pick(nombresM.concat(nombresH), i);
    const apellido = `${pick(apellidos, i)} ${pick(apellidos, i + 4)}`;
    const ci = `DOC${pad(i, 5)}`;
    docentes.push([
      ci,
      nombre,
      apellido,
      `${slug(nombre)}.${slug(apellido)}.${ci.toLowerCase()}@test.cup`,
      `7${pad(1000000 + i, 7)}`,
      1,
    ]);
  }

  const postulantes = [];
  const notas = [];
  for (let i = 1; i <= TOTAL_POSTULANTES; i++) {
    const genero = i % 2 === 0 ? 'masculino' : 'femenino';
    const nombre = genero === 'masculino' ? pick(nombresH, i) : pick(nombresM, i);
    const paterno = pick(apellidos, i + 1);
    const materno = pick(apellidos, i + 5);
    const ci = String(9000000 + i);
    const codigoCup = `CUP-${ANIO}-${pad(i, 5)}`;
    const carrera1 = pick(carreras, i);
    const carrera2 = pick(carreras.filter((carrera) => carrera !== carrera1), i + 1);

    postulantes.push([
      ci,
      '',
      nombre,
      paterno,
      materno,
      `2005-${pad((i % 12) + 1, 2)}-${pad((i % 27) + 1, 2)}`,
      genero,
      `${slug(nombre)}.${slug(paterno)}.${ci}@test.cup`,
      `6${pad(2000000 + i, 7)}`,
      `Barrio ${pick(['Plan 3000', 'Hamacas', 'Equipetrol', 'Los Tusequis', 'Villa Primero de Mayo'], i)} #${100 + i}`,
      pick(colegios, i),
      pick(ciudades, i),
      GESTION,
      codigoCup,
      carrera1,
      carrera2,
    ]);

    materias.forEach((materia, materiaIndex) => {
      const ex1 = notaFor(i, materiaIndex, 1);
      let ex2 = notaFor(i, materiaIndex, 2);
      let ex3 = notaFor(i, materiaIndex, 3);
      if (ex1 < 60) {
        ex2 = '';
        ex3 = '';
      } else if (ex2 < 60) {
        ex3 = '';
      }
      const promedio = promedioNotas(ex1, ex2, ex3);
      notas.push([
        codigoCup,
        ci,
        materia,
        ex1,
        ex2,
        ex3,
        promedio,
        estadoNotas(ex1, ex2, ex3),
      ]);
    });
  }

  const guia = [
    ['Objetivo', 'Datos sinteticos para probar registro, docentes y evaluaciones CUP.'],
    ['Postulantes generados', TOTAL_POSTULANTES],
    ['Docentes generados', TOTAL_DOCENTES],
    ['Materias por postulante', materias.length],
    ['Filas de notas', notas.length],
    ['Regla de aprobacion', 'Promedio >= 60 y sin reprobacion de materia.'],
    ['Correos', 'Todos usan dominio @test.cup y son unicos.'],
    ['Codigos CUP', 'CUP-2026-00001 hasta CUP-2026-03000.'],
    ['Uso recomendado', 'Importar primero docentes/postulantes, luego grupos/parametros y finalmente notas_examenes.'],
  ];

  return { docentes, postulantes, notas, guia };
}

async function writeFile(relativePath, content) {
  const filePath = path.join(buildDir, relativePath);
  await fs.mkdir(path.dirname(filePath), { recursive: true });
  await fs.writeFile(filePath, content, 'utf8');
}

async function main() {
  await fs.rm(buildDir, { recursive: true, force: true });
  await fs.mkdir(outputDir, { recursive: true });
  await fs.rm(outputXlsx, { force: true });
  await fs.rm(outputZip, { force: true });

  const { docentes, postulantes, notas, guia } = buildData();
  const sheets = [
    { name: 'guia_carga', headers: ['campo', 'detalle'], rows: guia },
    { name: 'postulantes', headers: ['ci', 'complemento', 'nombres', 'apellido_paterno', 'apellido_materno', 'fecha_nacimiento', 'genero', 'correo', 'telefono', 'direccion', 'colegio_procedencia', 'ciudad', 'gestion', 'codigo_cup', 'primera_opcion_carrera', 'segunda_opcion_carrera'], rows: postulantes },
    { name: 'docentes', headers: ['ci', 'nombres', 'apellidos', 'correo', 'telefono', 'activo'], rows: docentes },
    { name: 'notas_examenes', headers: ['codigo_cup', 'ci', 'materia', 'examen_1', 'examen_2', 'examen_3', 'promedio', 'estado_esperado'], rows: notas },
    { name: 'materias', headers: ['codigo', 'nombre', 'activa'], rows: [['MAT', 'Matematica', 1], ['COM', 'Computacion', 1], ['ING', 'Ingles', 1], ['FIS', 'Fisica', 1]] },
  ];

  await writeFile('[Content_Types].xml', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  ${sheets.map((_, index) => `<Override PartName="/xl/worksheets/sheet${index + 1}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>`).join('\n  ')}
</Types>`);
  await writeFile('_rels/.rels', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>`);
  await writeFile('xl/workbook.xml', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>${sheets.map((sheet, index) => `<sheet name="${sheet.name}" sheetId="${index + 1}" r:id="rId${index + 1}"/>`).join('')}</sheets>
</workbook>`);
  await writeFile('xl/_rels/workbook.xml.rels', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  ${sheets.map((_, index) => `<Relationship Id="rId${index + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet${index + 1}.xml"/>`).join('\n  ')}
  <Relationship Id="rId${sheets.length + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>`);
  await writeFile('xl/styles.xml', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>`);

  for (let i = 0; i < sheets.length; i++) {
    await writeFile(`xl/worksheets/sheet${i + 1}.xml`, sheetXml(sheets[i].headers, sheets[i].rows));
  }

  execFileSync('powershell.exe', ['-NoProfile', '-Command', `Compress-Archive -Path '${buildDir}\\*' -DestinationPath '${outputZip}' -Force`], { stdio: 'inherit' });
  await fs.rename(outputZip, outputXlsx);
  await fs.rm(buildDir, { recursive: true, force: true });

  console.log(JSON.stringify({
    outputXlsx,
    postulantes: postulantes.length,
    docentes: docentes.length,
    notas: notas.length,
  }, null, 2));
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
