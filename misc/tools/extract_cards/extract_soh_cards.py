import argparse
import numpy as np

import pypdfium2 as pdfium
from PIL import Image


def main():
    parser = argparse.ArgumentParser(description="Extract SOH cards from a PDF file")
    parser.add_argument("pdf_file", help="PDF file to extract SOH cards from")
    parser.add_argument("outfile", help="output file name")
    parser.add_argument(
        "--num-columns",
        type=int,
        default=6,
        help="Number of columns of cards to produce",
    )
    parser.add_argument(
        "--card-resolution",
        type=int,
        default=1.5,
        help="Resolution of the extracted cards in DPI, 72dpi per",
    )
    parser.add_argument(
        "--card-index-to-extract", nargs="+", type=int, help="Card indices to extract"
    )
    parser.add_argument(
        "--max-histogram-distance",
        type=int,
        default=120,
        help="Maximum histogram distance to consider a card the same",
    )
    parser.add_argument(
        "--crop",
        nargs=4,
        type=int,
        default=(30, 38, 30, 33),
        help="Crop the page to the given coordinates",
    )
    seen = []
    args = parser.parse_args()
    full_image = None
    pdf = pdfium.PdfDocument(args.pdf_file)  # load a PDF document
    n_pages = len(pdf)  # get the number of pages in the document
    print(f"Found {n_pages} pages in {args.pdf_file}")
    images = []
    row = 0
    col = 0
    for p in range(n_pages):
        if args.card_index_to_extract and p not in args.card_index_to_extract:
            continue
        page = pdf[p]  # load a page
        bitmap = page.render(
            scale=args.card_resolution,
            crop=args.crop,  # crop the page
        )
        pil_image = bitmap.to_pil()
        h = np.array(pil_image.histogram())
        for s, p2 in seen:
            if np.linalg.norm(h - s) < args.max_histogram_distance:
                print(f"abs image dist: {np.linalg.norm(h - s)} between {p+1} and {p2+1}")
                break
        else:
            col += 1
            if col >= args.num_columns:
                col = 0
                row += 1
            seen.append((h, p))
            images.append(pil_image)

    print(f"cardsize: {pil_image.width}x{pil_image.height}")
    full_image = Image.new(
        "RGB",
        (
            pil_image.width * min(args.num_columns, len(images)),
            pil_image.height * (row + 1),
        ),
    )
    row = 0
    col = 0
    for pil_image in images:
        full_image.paste(pil_image, (col * pil_image.width, row * pil_image.height))
        col += 1
        if col >= args.num_columns:
            col = 0
            row += 1

    full_image.save(args.outfile)
    full_image.show()


if __name__ == "__main__":
    main()
