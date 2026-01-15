let debugLevel = 0;

export const setDebugLevel = (level: number): void => {
    debugLevel = Math.max(0, Math.floor(level));
};

export const debug = (level: number, line: string): void => {
    if (level > debugLevel) {
        return;
    }

    console.log(`[debug:${level}] ${line}`);
};

export const registerDebug = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    (window as unknown as { debug?: typeof debug }).debug = debug;
    (window as unknown as { set_debuglevel?: typeof setDebugLevel }).set_debuglevel = setDebugLevel;
};
