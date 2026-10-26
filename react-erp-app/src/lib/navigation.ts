import { useEffect, useState } from "react";
export function navigate(path: string) {
    window.location.hash = path;
}
export function useRoute() {
    const [path, setPath] = useState(window.location.hash.slice(1) || "/login");
    useEffect(() => {
        const update = () => setPath(window.location.hash.slice(1) || "/login");
        window.addEventListener("hashchange", update);
        return () => window.removeEventListener("hashchange", update);
    }, []);
    useEffect(() => {
        window.scrollTo(0, 0);
    }, [path]);
    return path;
}
