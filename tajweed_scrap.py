import requests
import os
import time

BASE_URL = "https://api.quran.com/api/v4/quran/verses/uthmani_tajweed?verse_key="
OUTPUT_DIR = "tajweed_data"
SURAH_AYAH_COUNTS = [
    0, 7, 286, 200, 176, 120, 165, 206, 75, 129, 109, 123, 111, 43, 52, 99, 128, 111, 110, 98, 135, 112, 78, 118, 64, 77, 227, 93, 88, 69,
    60, 34, 30, 73, 54, 45, 83, 182, 88, 75, 85, 54, 53, 89, 59, 37, 35, 38, 29, 18, 45, 60, 49, 62, 55, 78, 96, 29, 22, 24,
    13, 14, 11, 11, 18, 12, 12, 30, 52, 52, 44, 28, 28, 20, 56, 40, 31, 50, 40, 46, 42, 29, 19, 36, 25, 22, 17, 19, 26, 30,
    20, 15, 21, 11, 8, 8, 5, 19, 5, 8, 8, 11, 11, 8, 3, 9, 5, 4, 7, 3, 6, 3, 5, 4, 5, 6
]

def scrape_data():
    if not os.path.exists(OUTPUT_DIR):
        os.makedirs(OUTPUT_DIR)
    
    for surah in range(1, 115):
        surah_dir = os.path.join(OUTPUT_DIR, str(surah))
        if not os.path.exists(surah_dir):
            os.makedirs(surah_dir)
        
        for ayah in range(1, SURAH_AYAH_COUNTS[surah] + 1):
            file_path = os.path.join(surah_dir, f"{ayah}.html")
            if os.path.exists(file_path):
                print(f"Skipping {surah}:{ayah} (exists)")
                continue

            try:
                url = f"{BASE_URL}{surah}:{ayah}"
                response = requests.get(url, timeout=15)
                response.raise_for_status()
                data = response.json()
                tajweed_html = data['verses'][0]['text_uthmani_tajweed']
                
                with open(file_path, 'w', encoding='utf-8') as f:
                    f.write(tajweed_html)
                print(f"Saved {surah}:{ayah}")
                time.sleep(0.05)

            except Exception as e:
                print(f"Error on {surah}:{ayah}: {e}")

if __name__ == "__main__":
    print("Starting Tajweed data download...")
    scrape_data()
    print("Download complete.")