import io
import os
import zipfile

import requests
import urllib3
from bs4 import BeautifulSoup
from flask import Flask, jsonify, render_template, request, send_file
from urllib.parse import urljoin, urlparse

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

app = Flask(__name__)

HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/120.0.0.0 Safari/537.36"
    ),
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
    "Accept-Language": "ja,en-US;q=0.7,en;q=0.3",
}

CONTENT_TYPE_TO_EXT = {
    "image/jpeg": ".jpg",
    "image/jpg": ".jpg",
    "image/png": ".png",
    "image/gif": ".gif",
    "image/webp": ".webp",
    "image/svg+xml": ".svg",
    "image/bmp": ".bmp",
    "image/tiff": ".tiff",
    "image/avif": ".avif",
}

IMG_ATTRS = ["src", "data-src", "data-lazy-src", "data-original", "data-url", "data-lazy"]


@app.route("/")
def index():
    return render_template("index.html")


@app.route("/scrape", methods=["POST"])
def scrape():
    data = request.get_json()
    url = (data.get("url") or "").strip()

    if not url:
        return jsonify({"success": False, "error": "URLが入力されていません"})

    if not url.startswith(("http://", "https://")):
        url = "https://" + url

    try:
        resp = requests.get(url, headers=HEADERS, timeout=15, verify=False)
        resp.raise_for_status()
        resp.encoding = resp.apparent_encoding

        soup = BeautifulSoup(resp.text, "html.parser")

        images = []
        seen = set()

        for img_tag in soup.find_all("img"):
            src = None
            for attr in IMG_ATTRS:
                val = img_tag.get(attr, "").strip()
                if val and not val.startswith("data:"):
                    src = val
                    break

            if not src:
                continue

            full_url = urljoin(url, src)
            if not full_url.startswith("http") or full_url in seen:
                continue

            seen.add(full_url)
            parsed = urlparse(full_url)
            filename = os.path.basename(parsed.path) or f"image_{len(images) + 1}.jpg"
            if not os.path.splitext(filename)[1]:
                filename += ".jpg"

            images.append({
                "url": full_url,
                "alt": img_tag.get("alt", ""),
                "filename": filename,
                "source": url,
            })

        page_title = soup.title.string.strip() if soup.title and soup.title.string else url

        return jsonify({
            "success": True,
            "images": images,
            "count": len(images),
            "page_title": page_title,
        })

    except requests.exceptions.Timeout:
        return jsonify({"success": False, "error": "タイムアウトしました。URLを確認してください。"})
    except requests.exceptions.SSLError:
        return jsonify({"success": False, "error": "SSL証明書エラーが発生しました。"})
    except requests.exceptions.ConnectionError:
        return jsonify({"success": False, "error": "接続できませんでした。URLを確認してください。"})
    except Exception as e:
        return jsonify({"success": False, "error": f"エラーが発生しました: {e}"})


@app.route("/download", methods=["POST"])
def download():
    data = request.get_json()
    selected = data.get("images", [])

    if not selected:
        return jsonify({"success": False, "error": "画像が選択されていません"}), 400

    zip_buffer = io.BytesIO()
    filename_counts: dict[str, int] = {}

    with zipfile.ZipFile(zip_buffer, "w", zipfile.ZIP_DEFLATED) as zf:
        for item in selected:
            try:
                img_resp = requests.get(item["url"], headers=HEADERS, timeout=10, verify=False)
                img_resp.raise_for_status()

                filename = item.get("filename", "image.jpg")
                base, ext = os.path.splitext(filename)

                if not ext:
                    ct = img_resp.headers.get("content-type", "").split(";")[0].strip()
                    ext = CONTENT_TYPE_TO_EXT.get(ct, ".jpg")

                candidate = f"{base}{ext}"
                if candidate in filename_counts:
                    filename_counts[candidate] += 1
                    final_name = f"{base}_{filename_counts[candidate]}{ext}"
                else:
                    filename_counts[candidate] = 0
                    final_name = candidate

                zf.writestr(final_name, img_resp.content)
            except Exception:
                continue

    zip_buffer.seek(0)
    return send_file(
        zip_buffer,
        mimetype="application/zip",
        as_attachment=True,
        download_name="scraped_images.zip",
    )


if __name__ == "__main__":
    app.run(debug=True, host="0.0.0.0", port=5000)
