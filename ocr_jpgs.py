import sys
from pathlib import Path
from typing import List

try:
    from rapidocr_onnxruntime import RapidOCR
except Exception as e:
    print("RapidOCR import failed:", e)
    sys.exit(1)


def collect_images(folder: Path) -> List[Path]:
    exts = {".jpg", ".jpeg", ".png", ".bmp"}
    files = sorted([p for p in folder.iterdir() if p.suffix.lower() in exts])
    return files


def main() -> int:
    base = Path(__file__).parent
    img_dir = base / "dokumen"
    if not img_dir.exists():
        print(f"Folder tidak ditemukan: {img_dir}")
        return 2

    images = collect_images(img_dir)
    if not images:
        print("Tidak ada gambar untuk diproses.")
        return 3

    ocr = RapidOCR()
    out_path = base / "dokumen_text.txt"
    total_chars = 0

    with out_path.open("w", encoding="utf-8") as f:
        for i, img_path in enumerate(images, 1):
            try:
                result, _ = ocr(img_path.as_posix())
                # result is list: [[box, text, score], ...]
                lines = []
                if result:
                    for _, text, score in result:
                        if text:
                            lines.append(text)
                page_text = "\n".join(lines).strip()
                total_chars += len(page_text)
                f.write(f"\n\n===== HALAMAN {i}: {img_path.name} =====\n")
                f.write(page_text + "\n")
                print(f"OK {i}/{len(images)}: {img_path.name} ({len(page_text)} chars)")
            except Exception as e:
                print(f"Gagal memproses {img_path}: {e}")

    print(f"Selesai. Output: {out_path} (~{total_chars} chars)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
