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
    seen = []
    args = parser.parse_args()
    full_image = None
    pdf = pdfium.PdfDocument(args.pdf_file)  # load a PDF document
    n_pages = len(pdf)  # get the number of pages in the document
    print(n_pages)
    images = []
    row = 0
    col = 0
    for p in range(n_pages):
        page = pdf[p]  # load a page
        bitmap = page.render(
            scale=args.card_resolution,
            crop=(30, 38, 30, 33),  # crop the page
        )
        pil_image = bitmap.to_pil()
        h = np.array(pil_image.histogram())
        for s in seen:
            if np.linalg.norm(h - s) < 120:
                break
        else:
            col += 1
            if col >= args.num_columns:
                col = 0
                row += 1
            seen.append(h)
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
