export function useHashids() {
    const charset =
        "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_".split(
            "",
        );
    const decodeHashid = (str: string): string => {
        const base = BigInt(64);
        const value = str
            .split("")
            .reverse()
            .reduce(
                (prev, char, i) =>
                    prev + BigInt(charset.indexOf(char)) * base ** BigInt(i),
                BigInt(0),
            )
            .toString(10);
        return value;
    };

    const encodeHashid = (id: string): string => {
        const base = BigInt(64);
        let num = BigInt(id);
        let result = "";

        while (num > 0) {
            const remainder = num % base;
            result = charset[Number(remainder)] + result;
            num = num / base;
        }

        return result || "0";
    };

    return {
        decodeHashid,
        encodeHashid,
    };
}
