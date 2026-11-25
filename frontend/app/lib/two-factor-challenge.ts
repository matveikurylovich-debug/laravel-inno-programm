let challengeId = "";

export function rememberChallengeId(id: string): void {
  challengeId = id;
}

export function currentChallengeId(): string {
  return challengeId;
}
