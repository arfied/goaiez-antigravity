if (!process.env.WS_CLONE_FROM || !process.env.WS_AUTH_SECRET) {
  process.exit(3);
}

const out = {
  projectId: "fake-clone-" + process.env.WS_CLONE_FROM,
  token: "fake-token",
  shareLink: "https://fake",
  editorUrl: "https://fake"
};

console.log(JSON.stringify(out));
process.exit(0);
