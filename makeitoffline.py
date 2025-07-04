import os
import re
import shutil
import requests
from pathlib import Path
from bs4 import BeautifulSoup
from urllib.parse import urlparse, urljoin, unquote

def process_resource(url, base_url, project_root, backup_root, processed_urls):
    if not url or url.startswith(('data:', 'blob:', 'mailto:', 'tel:', '#', 'javascript:')):
        return None

    try:
        full_url = urljoin(base_url, url)
        if full_url in processed_urls:
            return processed_urls[full_url]

        print(f"Processing: {url[:100]}")
        is_remote = urlparse(full_url).scheme in ['http', 'https'
        ]
        if is_remote:
            parsed_path = Path(unquote(urlparse(full_url).path))
            filename = parsed_path.name or "index.html"
            resource_dir = backup_root / urlparse(full_url).netloc / parsed_path.parent.relative_to(parsed_path.anchor)
            resource_dir.mkdir(parents=True, exist_ok=True)
            local_path = resource_dir / filename
            
            with requests.get(full_url, timeout=20, headers={'User-Agent': 'Mozilla/5.0'}) as r:
                r.raise_for_status()
                with open(local_path, 'wb') as f:
                    f.write(r.content)
        else:
            source_path = (project_root / url).resolve()
            if not source_path.is_file(): return None

            relative_to_root = source_path.relative_to(project_root)
            local_path = (backup_root / relative_to_root).resolve()
            local_path.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(source_path, local_path)
        
        new_rel_path = Path(os.path.relpath(local_path, backup_root)).as_posix()
        processed_urls[full_url] = new_rel_path

        if local_path.suffix.lower() == '.css':
            parse_css_for_urls(local_path, full_url, project_root, backup_root, processed_urls)
        elif local_path.suffix.lower() in ['.js', '.mjs']:
            parse_js_for_urls(local_path.read_text(encoding='utf-8', errors='ignore'), full_url, project_root, backup_root, processed_urls)

        return new_rel_path

    except Exception as e:
        print(f"  -> Failed to process '{url[:100]}': {e}")
        processed_urls[full_url] = url 
        return None

def parse_css_for_urls(css_path, css_base_url, project_root, backup_root, processed_urls):
    content = css_path.read_text(encoding='utf-8', errors='ignore')
    
    def replacer(match):
        sub_url = match.group(1).strip("'\"")
        new_sub_path = process_resource(sub_url, css_base_url, project_root, backup_root, processed_urls)
        if new_sub_path:
            relative_to_css = Path(os.path.relpath(backup_root / new_sub_path, css_path.parent)).as_posix()
            return f'url("{relative_to_css}")'
        return match.group(0)

    content = re.sub(r'url\((.*?)\)', replacer, content, flags=re.IGNORECASE)
    css_path.write_text(content, encoding='utf-8')

def parse_js_for_urls(js_content, js_base_url, project_root, backup_root, processed_urls):
    pattern = r'[\'"`]([.\/]*[\w\s\.\-\/]+\.\w{2,})[\'"`]'
    found_urls = re.findall(pattern, js_content)
    for url in found_urls:
        print(f"  -> Found in JS: {url}")
        process_resource(url, js_base_url, project_root, backup_root, processed_urls)

def backup_app(html_file, backup_folder_name="app_backup"):
    html_path = Path(html_file).resolve()
    if not html_path.is_file():
        print(f"Error: HTML file not found at {html_path}")
        return

    project_root = html_path.parent
    backup_root = project_root / backup_folder_name
    if backup_root.exists(): shutil.rmtree(backup_root)
    backup_root.mkdir(exist_ok=True)
    
    processed_urls = {}
    html_base_url = html_path.as_uri()
    
    soup = BeautifulSoup(html_path.read_text(encoding='utf-8', errors='ignore'), 'html.parser')

    tags_to_process = {
        'link': ['href'], 'script': ['src'], 'img': ['src'],
        'source': ['src', 'srcset'], 'video': ['src', 'poster'], 'a': ['href']
    }
    for tag_name, attrs in tags_to_process.items():
        for tag in soup.find_all(tag_name):
            for attr in attrs:
                if not tag.has_attr(attr): continue
                original_value = tag[attr]
                if attr == 'srcset':
                    new_srcset_parts = []
                    for part in original_value.split(','):
                        url, *descriptor = part.strip().split(maxsplit=1)
                        if not url: continue
                        new_path = process_resource(url, html_base_url, project_root, backup_root, processed_urls)
                        new_srcset_parts.append(f"{new_path or url} {''.join(descriptor)}")
                    tag[attr] = ", ".join(new_srcset_parts)
                else:
                    new_path = process_resource(original_value, html_base_url, project_root, backup_root, processed_urls)
                    if new_path: tag[attr] = new_path

    def css_url_replacer(match):
        url = match.group(1).strip("'\"")
        new_path = process_resource(url, html_base_url, project_root, backup_root, processed_urls)
        return f'url("{new_path or url}")'

    for tag in soup.find_all(style=True):
        tag['style'] = re.sub(r'url\((.*?)\)', css_url_replacer, tag['style'], flags=re.IGNORECASE)

    for tag in soup.find_all('style'):
        if tag.string:
            tag.string = re.sub(r'url\((.*?)\)', css_url_replacer, tag.string, flags=re.IGNORECASE)
            tag.string = re.sub(r'@import\s+url\((.*?)\);', css_url_replacer, tag.string, flags=re.IGNORECASE)

    for tag in soup.find_all('script'):
        if not tag.has_attr('src') and tag.string:
            parse_js_for_urls(tag.string, html_base_url, project_root, backup_root, processed_urls)

    new_html_path = backup_root / html_path.name
    new_html_path.write_text(str(soup.prettify()), encoding='utf-8')

    print(f"\nBackup complete! Check the '{backup_folder_name}' folder.")

if __name__ == "__main__":
    html_file_to_backup = 'index.html'
    backup_app(html_file_to_backup)