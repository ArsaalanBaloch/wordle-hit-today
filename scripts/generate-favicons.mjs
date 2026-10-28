import { mkdir, readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { deflateSync } from "node:zlib";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.dirname(scriptDirectory);
const brandDirectory = path.join(projectRoot, "assets", "brand");

await mkdir(brandDirectory, { recursive: true });
await readFile(path.join(brandDirectory, "favicon.svg"));

const png16 = renderPng(16);
const png32 = renderPng(32);
const png48 = renderPng(48);
const appleTouchIcon = renderPng(180);

await writeFile(path.join(brandDirectory, "favicon-48x48.png"), png48);
await writeFile(
  path.join(brandDirectory, "apple-touch-icon.png"),
  appleTouchIcon,
);
await writeFile(
  path.join(brandDirectory, "favicon.ico"),
  createIco([
    { size: 16, png: png16 },
    { size: 32, png: png32 },
    { size: 48, png: png48 },
  ]),
);

function renderPng(size) {
  const sampleScale = 4;
  const sampledSize = size * sampleScale;
  const sampled = Buffer.alloc(sampledSize * sampledSize * 4);
  const colors = {
    dark: [23, 79, 49, 255],
    cream: [250, 250, 247, 255],
    green: [37, 109, 69, 255],
    white: [255, 255, 255, 255],
  };

  for (let y = 0; y < sampledSize; y += 1) {
    for (let x = 0; x < sampledSize; x += 1) {
      const nx = (x + 0.5) / sampledSize;
      const ny = (y + 0.5) / sampledSize;
      let color = [0, 0, 0, 0];

      if (insideRoundedRect(nx, ny, 0, 0, 1, 1, 0.22)) {
        color = colors.dark;
      }
      if (insideRoundedRect(nx, ny, 0.055, 0.055, 0.89, 0.89, 0.18)) {
        color = colors.cream;
      }
      if (insideRoundedRect(nx, ny, 0.095, 0.095, 0.81, 0.81, 0.14)) {
        color = colors.green;
      }

      const w = [
        [0.2, 0.35],
        [0.26, 0.35],
        [0.31, 0.65],
        [0.36, 0.46],
        [0.405, 0.65],
        [0.46, 0.35],
        [0.52, 0.35],
        [0.44, 0.72],
        [0.38, 0.72],
        [0.335, 0.55],
        [0.29, 0.72],
        [0.23, 0.72],
      ];
      const inH =
        (nx >= 0.57 && nx <= 0.63 && ny >= 0.35 && ny <= 0.72) ||
        (nx >= 0.72 && nx <= 0.78 && ny >= 0.35 && ny <= 0.72) ||
        (nx >= 0.63 && nx <= 0.72 && ny >= 0.5 && ny <= 0.57);
      if (pointInPolygon(nx, ny, w) || inH) {
        color = colors.white;
      }

      const offset = (y * sampledSize + x) * 4;
      sampled.set(color, offset);
    }
  }

  const pixels = Buffer.alloc(size * size * 4);
  for (let y = 0; y < size; y += 1) {
    for (let x = 0; x < size; x += 1) {
      const totals = [0, 0, 0, 0];
      for (let sy = 0; sy < sampleScale; sy += 1) {
        for (let sx = 0; sx < sampleScale; sx += 1) {
          const sampledOffset =
            ((y * sampleScale + sy) * sampledSize + x * sampleScale + sx) * 4;
          for (let channel = 0; channel < 4; channel += 1) {
            totals[channel] += sampled[sampledOffset + channel];
          }
        }
      }
      const outputOffset = (y * size + x) * 4;
      for (let channel = 0; channel < 4; channel += 1) {
        pixels[outputOffset + channel] = Math.round(
          totals[channel] / sampleScale ** 2,
        );
      }
    }
  }

  return encodePng(size, size, pixels);
}

function insideRoundedRect(x, y, left, top, width, height, radius) {
  const right = left + width;
  const bottom = top + height;
  if (x < left || x > right || y < top || y > bottom) return false;
  const nearestX = Math.max(left + radius, Math.min(x, right - radius));
  const nearestY = Math.max(top + radius, Math.min(y, bottom - radius));
  return (x - nearestX) ** 2 + (y - nearestY) ** 2 <= radius ** 2;
}

function pointInPolygon(x, y, points) {
  let inside = false;
  for (
    let index = 0, previous = points.length - 1;
    index < points.length;
    previous = index, index += 1
  ) {
    const [currentX, currentY] = points[index];
    const [previousX, previousY] = points[previous];
    if (
      currentY > y !== previousY > y &&
      x <
        ((previousX - currentX) * (y - currentY)) / (previousY - currentY) +
          currentX
    ) {
      inside = !inside;
    }
  }
  return inside;
}

function encodePng(width, height, pixels) {
  const rows = Buffer.alloc((width * 4 + 1) * height);
  for (let y = 0; y < height; y += 1) {
    const rowOffset = y * (width * 4 + 1);
    rows[rowOffset] = 0;
    pixels.copy(rows, rowOffset + 1, y * width * 4, (y + 1) * width * 4);
  }
  const header = Buffer.alloc(13);
  header.writeUInt32BE(width, 0);
  header.writeUInt32BE(height, 4);
  header.set([8, 6, 0, 0, 0], 8);
  return Buffer.concat([
    Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
    pngChunk("IHDR", header),
    pngChunk("IDAT", deflateSync(rows, { level: 9 })),
    pngChunk("IEND", Buffer.alloc(0)),
  ]);
}

function pngChunk(type, data) {
  const typeBuffer = Buffer.from(type, "ascii");
  const chunk = Buffer.alloc(12 + data.length);
  chunk.writeUInt32BE(data.length, 0);
  typeBuffer.copy(chunk, 4);
  data.copy(chunk, 8);
  chunk.writeUInt32BE(
    crc32(Buffer.concat([typeBuffer, data])),
    8 + data.length,
  );
  return chunk;
}

function crc32(buffer) {
  let crc = 0xffffffff;
  for (const byte of buffer) {
    crc ^= byte;
    for (let bit = 0; bit < 8; bit += 1) {
      crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
    }
  }
  return (crc ^ 0xffffffff) >>> 0;
}

function createIco(images) {
  const headerSize = 6;
  const entrySize = 16;
  let offset = headerSize + entrySize * images.length;
  const header = Buffer.alloc(headerSize);
  header.writeUInt16LE(0, 0);
  header.writeUInt16LE(1, 2);
  header.writeUInt16LE(images.length, 4);

  const entries = images.map(({ size, png }) => {
    const entry = Buffer.alloc(entrySize);
    entry.writeUInt8(size === 256 ? 0 : size, 0);
    entry.writeUInt8(size === 256 ? 0 : size, 1);
    entry.writeUInt8(0, 2);
    entry.writeUInt8(0, 3);
    entry.writeUInt16LE(1, 4);
    entry.writeUInt16LE(32, 6);
    entry.writeUInt32LE(png.length, 8);
    entry.writeUInt32LE(offset, 12);
    offset += png.length;
    return entry;
  });

  return Buffer.concat([header, ...entries, ...images.map(({ png }) => png)]);
}
