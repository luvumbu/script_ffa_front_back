class AsciiConverter {
    /**
     * Convert a string of ASCII values (comma-separated) into a normal string.
     * Example: AsciiConverter.asciiToString(asciiString)
     * 
     * @param {string} asciiString - The ASCII values as a comma-separated string (e.g., "72, 101, 108").
     * @returns {string} - The decoded string (e.g., "Hello").
     */
    static asciiToString(asciiString) {
        // Supprimer les espaces inutiles et séparer les valeurs par des virgules
        const asciiArray = asciiString.split(',').map(value => value.trim());
        let resultString = '';

        asciiArray.forEach(ascii => {
            // Vérifier si l'entrée est un nombre valide
            if (!isNaN(ascii)) {
                resultString += String.fromCharCode(Number(ascii));
            }
        });

        return resultString;
    }

    /**
     * Convert a normal string into a string of ASCII values (comma-separated).
     * Example: AsciiConverter.stringToAscii(string)
     * 
     * @param {string} string - The string to convert (e.g., "Hello").
     * @returns {string} - The ASCII values as a comma-separated string (e.g., "72,101,108,108,111").
     */
    static stringToAscii(string) {
        const asciiArray = [];

        // Parcourir chaque caractère de la chaîne
        for (let i = 0; i < string.length; i++) {
            // Convertir le caractère en valeur ASCII
            asciiArray.push(string.charCodeAt(i));
        }

        // Joindre les valeurs ASCII avec des virgules
        return asciiArray.join(',');
    }
}

/*

// Exemple d'utilisation
const asciiString = "72, 101, 108, 108, 111";
const string = "Hello";

// Conversion de ASCII à chaîne de caractères
const decodedString = AsciiConverter.asciiToString(asciiString);
console.log(decodedString); // Affiche "Hello"

// Conversion de chaîne de caractères à ASCII
const asciiValues = AsciiConverter.stringToAscii(string);
console.log(asciiValues); // Affiche "72,101,108,108,111"
*/