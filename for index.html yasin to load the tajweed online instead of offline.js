    /* 
    async function getAyahHTML(surah, ayah) {
        if (fullScreenReaderSettings.showTajweedColors) {
            try {
                const response = await fetch(`https://api.quran.com/api/v4/quran/verses/uthmani_tajweed?verse_key=${surah}:${ayah}`);
                if (!response.ok) {
                    throw new Error(`API fetch failed with status ${response.status}`);
                }
                const data = await response.json();
                if (data.verses && data.verses.length > 0) {
                    const originalHtml = data.verses[0].text_uthmani_tajweed;
            const htmlWithoutVerseNumber = originalHtml.replace(/\s*﴿[٠-٩]+﴾\s*\/g, ''); 
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = htmlWithoutVerseNumber;
                    const tajweedElements = tempDiv.querySelectorAll('tajweed');
                    tajweedElements.forEach(el => {
                        const span = document.createElement('span');
                        span.className = `tajweed ${el.getAttribute('class')}`;
                        span.innerHTML = el.innerHTML;
                        el.parentNode.replaceChild(span, el);
                    });
                    return tempDiv.innerHTML;
                }
            } catch (error) {
                console.warn(`Tajweed API fetch failed for ${surah}:${ayah}. Falling back to plain text.`, error);
            }
        }
        const ayahData = await getData(STORE_QURAN, [surah, ayah]);
        return ayahData ? ayahData.arabic.trim() : `[Ayah ${surah}:${ayah} not found]`;
    } */