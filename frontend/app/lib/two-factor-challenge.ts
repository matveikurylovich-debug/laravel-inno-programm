let challengeId = "";
const listeners = new Set<() => void>();

export function rememberChallengeId(id: string): void {
  challengeId = id;
  listeners.forEach((listener) => listener());
}

export function currentChallengeId(): string {
  return challengeId;
}

export function subscribeChallengeId(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}
