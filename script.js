function convertText() {
    const text = document.getElementById("inputText").value;

    const words = text.split(/(\s+)/);

    const converted = words.map(word => {
        if (!word.trim()) return word; 

        const cleanWord = word.replace(/[^a-zA-Z0-9]/g, "");
        if (cleanWord.length === 0) return word;

        const boldLength = Math.ceil(cleanWord.length * 0.4);

        let prefix = "";
        let suffix = "";

        let count = 0;
        for (let i = 0; i < word.length; i++) {
            if (/[a-zA-Z0-9]/.test(word[i]) && count < boldLength) {
                prefix += word[i];
                count++;
            } else {
                suffix = word.slice(i);
                break;
            }
        }

        return `<strong>${prefix}</strong>${suffix}`;
    }).join("");

    document.getElementById("output").innerHTML = converted;
}
