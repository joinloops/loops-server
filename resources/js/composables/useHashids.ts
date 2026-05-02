export function useHashids() {
    const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_'
    const charset = ALPHABET.split('')
    const charMap = new Map(charset.map((c, i) => [c, i]))
    const BASE = BigInt(64)

    const decodeHashid = (str: string): string => {
        let result = BigInt(0)
        for (const char of str) {
            const idx = charMap.get(char)
            if (idx === undefined) {
                throw new Error(`Invalid hashid character: ${char}`)
            }
            result = result * BASE + BigInt(idx)
        }
        return result.toString(10)
    }

    const encodeHashid = (id: string | number): string => {
        let num = BigInt(id)
        if (num < 0n) {
            throw new Error('Input must be a positive integer')
        }
        if (num === 0n) return charset[0]

        let result = ''
        while (num > 0n) {
            result = charset[Number(num % BASE)] + result
            num = num / BASE
        }
        return result
    }

    return { decodeHashid, encodeHashid }
}
