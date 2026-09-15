import os
import sys
import hashlib
import fitz # PyMuPDF
from PIL import Image

def get_image_hash(img):
    """Calculate MD5 hash of image pixels to detect duplicates."""
    return hashlib.md5(img.tobytes()).hexdigest()

def extract_image_with_smask(doc, xref, smask_xref):
    """Extract embedded PDF image and apply soft mask (alpha channel) if present."""
    try:
        base_pix = fitz.Pixmap(doc, xref)
        
        # Convert base image to RGB colorspace
        if base_pix.colorspace.n != 3 or base_pix.alpha:
            base_rgb = fitz.Pixmap(fitz.csRGB, base_pix)
        else:
            base_rgb = base_pix

        img_base = Image.frombytes("RGB", [base_rgb.width, base_rgb.height], base_rgb.samples)
        
        # Apply soft mask (alpha channel) if available
        if smask_xref > 0:
            mask_pix = fitz.Pixmap(doc, smask_xref)
            if mask_pix.colorspace.n != 1:
                mask_gray = fitz.Pixmap(fitz.csGRAY, mask_pix)
            else:
                mask_gray = mask_pix
                
            mask_img = Image.frombytes("L", [mask_gray.width, mask_gray.height], mask_gray.samples)
            
            if mask_img.size != img_base.size:
                mask_img = mask_img.resize(img_base.size, Image.Resampling.LANCZOS)
                
            img_base.putalpha(mask_img)
            
        return img_base
    except Exception as e:
        print(f"    [!] Error extracting xref {xref}: {e}")
        return None

def process_brochure(pdf_path, out_scooters_dir, out_pages_dir):
    pdf_filename = os.path.basename(pdf_path)
    prefix = os.path.splitext(pdf_filename)[0].lower()
    prefix = prefix.replace(" ", "_").replace("-", "_").replace("'", "").replace("(", "").replace(")", "")
    
    print(f"\n=======================================================")
    print(f"Processing Brochure: {pdf_filename}")
    print(f"Prefix: {prefix}")
    print(f"=======================================================")

    doc = fitz.open(pdf_path)
    seen_hashes = set()
    extracted_count = 0

    for page_idx in range(len(doc)):
        page = doc[page_idx]
        image_list = page.get_images(full=True)
        
        # Save full page render for reference
        pix_page = page.get_pixmap(dpi=150)
        page_img_path = os.path.join(out_pages_dir, f"{prefix}_page_{page_idx + 1}.png")
        pix_page.save(page_img_path)

        for img_info in image_list:
            xref = img_info[0]
            smask_xref = img_info[1]
            w = img_info[2]
            h = img_info[3]

            # Filter out tiny icon elements
            if w < 150 or h < 150:
                continue

            # Don't process standalone soft masks directly (they get processed with base image)
            if img_info[5] == 'DeviceGray' and smask_xref == 0:
                # Check if this gray image is used as smask elsewhere
                continue

            img = extract_image_with_smask(doc, xref, smask_xref)
            if img is None:
                continue

            # Auto-crop transparent boundaries
            if img.mode == "RGBA":
                bbox = img.getbbox()
                if bbox:
                    # Check cropped size
                    cropped_w = bbox[2] - bbox[0]
                    cropped_h = bbox[3] - bbox[1]
                    if cropped_w >= 100 and cropped_h >= 100:
                        img = img.crop(bbox)

            # Deduplicate
            img_hash = get_image_hash(img)
            if img_hash in seen_hashes:
                continue
            seen_hashes.add(img_hash)

            extracted_count += 1
            filename = f"{prefix}_scooter_{extracted_count}.png"
            out_path = os.path.join(out_scooters_dir, filename)
            
            img.save(out_path, "PNG")
            print(f"  [+] Saved ({img.width}x{img.height}, mode={img.mode}): {filename}")

    print(f"--> Extracted {extracted_count} unique product/scooter images from {pdf_filename}")
    return extracted_count

def main():
    broucher_dir = r"d:\hazra_ev\Broucher"
    
    # Destination directories
    out_scooters_dir = r"d:\hazra_ev\pictures\scooters"
    out_pages_dir = r"d:\hazra_ev\pictures\pages"
    website_assets_scooters = r"d:\hazra_ev\website\assets\scooters"

    os.makedirs(out_scooters_dir, exist_ok=True)
    os.makedirs(out_pages_dir, exist_ok=True)
    os.makedirs(website_assets_scooters, exist_ok=True)

    pdf_files = [f for f in os.listdir(broucher_dir) if f.lower().endswith(".pdf")]
    
    total_extracted = 0
    for pdf_file in pdf_files:
        pdf_path = os.path.join(broucher_dir, pdf_file)
        count = process_brochure(pdf_path, out_scooters_dir, out_pages_dir)
        total_extracted += count

    # Also copy all extracted scooters into website assets
    for img_name in os.listdir(out_scooters_dir):
        src = os.path.join(out_scooters_dir, img_name)
        dst = os.path.join(website_assets_scooters, img_name)
        with open(src, 'rb') as fsrc:
            with open(dst, 'wb') as fdst:
                fdst.write(fsrc.read())

    print(f"\n=======================================================")
    print(f"SUCCESS: Processed {len(pdf_files)} brochures.")
    print(f"Extracted {total_extracted} total high-res transparent PNG cutouts.")
    print(f"Images saved to: {out_scooters_dir}")
    print(f"Images copied to: {website_assets_scooters}")
    print(f"=======================================================")

if __name__ == "__main__":
    main()
