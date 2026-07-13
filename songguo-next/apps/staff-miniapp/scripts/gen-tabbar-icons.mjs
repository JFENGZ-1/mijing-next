import fs from "node:fs";
import path from "node:path";
import zlib from "node:zlib";
import { fileURLToPath } from "node:url";

const SIZE = 81;
const OUT_DIR = path.join(path.dirname(fileURLToPath(import.meta.url)), "../src/static/tabbar");

function hexToRgb(hex) {
  const value = Number.parseInt(hex.slice(1), 16);
  return [(value >> 16) & 255, (value >> 8) & 255, value & 255];
}

function createCanvas() {
  return new Uint8Array(SIZE * SIZE * 4);
}

function setPixel(data, x, y, rgb, alpha = 255) {
  if (x < 0 || y < 0 || x >= SIZE || y >= SIZE) return;
  const index = (y * SIZE + x) * 4;
  data[index] = rgb[0];
  data[index + 1] = rgb[1];
  data[index + 2] = rgb[2];
  data[index + 3] = alpha;
}

function fillRect(data, x, y, width, height, rgb, alpha = 255) {
  for (let row = 0; row < height; row += 1) {
    for (let col = 0; col < width; col += 1) {
      setPixel(data, x + col, y + row, rgb, alpha);
    }
  }
}

function strokeRect(data, x, y, width, height, rgb, thickness = 3) {
  fillRect(data, x, y, width, thickness, rgb);
  fillRect(data, x, y + height - thickness, width, thickness, rgb);
  fillRect(data, x, y, thickness, height, rgb);
  fillRect(data, x + width - thickness, y, thickness, height, rgb);
}

function drawDashboard(data, rgb) {
  const gap = 6;
  const tile = 24;
  const startX = 16;
  const startY = 18;
  for (let row = 0; row < 2; row += 1) {
    for (let col = 0; col < 2; col += 1) {
      fillRect(data, startX + col * (tile + gap), startY + row * (tile + gap), tile, tile, rgb);
    }
  }
}

function drawCourse(data, rgb) {
  strokeRect(data, 20, 18, 41, 46, rgb, 4);
  fillRect(data, 20, 28, 41, 4, rgb);
  for (const x of [30, 40, 50]) fillRect(data, x, 22, 4, 8, rgb);
  for (const y of [38, 48, 58]) {
    for (const x of [28, 40, 52]) fillRect(data, x, y, 6, 6, rgb);
  }
}

function encodePng(rgba) {
  const signature = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(SIZE, 0);
  ihdr.writeUInt32BE(SIZE, 4);
  ihdr[8] = 8;
  ihdr[9] = 6;
  ihdr[10] = 0;
  ihdr[11] = 0;
  ihdr[12] = 0;

  const raw = Buffer.alloc((SIZE * (1 + SIZE * 4)));
  let offset = 0;
  for (let y = 0; y < SIZE; y += 1) {
    raw[offset] = 0;
    offset += 1;
    for (let x = 0; x < SIZE; x += 1) {
      const index = (y * SIZE + x) * 4;
      raw[offset] = rgba[index];
      raw[offset + 1] = rgba[index + 1];
      raw[offset + 2] = rgba[index + 2];
      raw[offset + 3] = rgba[index + 3];
      offset += 4;
    }
  }

  const idat = zlib.deflateSync(raw, { level: 9 });
  const chunk = (type, data) => {
    const length = Buffer.alloc(4);
    length.writeUInt32BE(data.length, 0);
    const typeBuf = Buffer.from(type);
    const crcInput = Buffer.concat([typeBuf, data]);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(crcInput), 0);
    return Buffer.concat([length, typeBuf, data, crc]);
  };

  return Buffer.concat([
    signature,
    chunk("IHDR", ihdr),
    chunk("IDAT", idat),
    chunk("IEND", Buffer.alloc(0)),
  ]);
}

function crc32(buffer) {
  let crc = 0xffffffff;
  for (let index = 0; index < buffer.length; index += 1) {
    crc ^= buffer[index];
    for (let bit = 0; bit < 8; bit += 1) {
      crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
    }
  }
  return (crc ^ 0xffffffff) >>> 0;
}

function writeIcon(name, drawer, colorHex) {
  const data = createCanvas();
  drawer(data, hexToRgb(colorHex));
  fs.writeFileSync(path.join(OUT_DIR, name), encodePng(data));
}

fs.mkdirSync(OUT_DIR, { recursive: true });
writeIcon("dashboard.png", drawDashboard, "#667085");
writeIcon("dashboard-active.png", drawDashboard, "#1677ff");
writeIcon("course.png", drawCourse, "#667085");
writeIcon("course-active.png", drawCourse, "#1677ff");
console.log("Wrote tabbar icons to", OUT_DIR);
