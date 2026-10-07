import * as https from 'https';
import { URL } from 'url';

const origin = process.env.WS_BUILDER_ORIGIN;
const secret = process.env.WS_AUTH_SECRET;
const email = process.env.WS_SERVICE_EMAIL || 'hello@webstudio.is';
const title = process.env.WS_PROJECT_TITLE;

if (process.env.WS_INSECURE_TLS === '1') {
  process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
}

if (!origin || !secret || !title) {
  console.error("Missing required env vars");
  process.exit(1);
}

async function request(urlStr, options, bodyData = null) {
  return new Promise((resolve, reject) => {
    const url = new URL(urlStr);
    const reqOptions = {
      hostname: url.hostname,
      port: url.port,
      path: url.pathname + url.search,
      method: options.method,
      headers: options.headers || {},
    };

    const req = https.request(reqOptions, (res) => {
      let data = '';
      res.on('data', (chunk) => data += chunk);
      res.on('end', () => {
        resolve({
          status: res.statusCode,
          headers: res.headers,
          data
        });
      });
    });

    req.on('error', (e) => reject(e));

    if (bodyData) {
      req.write(bodyData);
    }
    req.end();
  });
}

async function run() {
  // 1. Login
  const loginBody = new URLSearchParams({ secret, email }).toString();
  const loginRes = await request(`${origin}/auth/dev`, {
    method: 'POST',
    headers: {
      'content-type': 'application/x-www-form-urlencoded',
      'sec-fetch-site': 'same-origin',
      'sec-fetch-mode': 'cors',
      'origin': origin,
      'content-length': Buffer.byteLength(loginBody)
    }
  }, loginBody);

  let cookiesArr = loginRes.headers['set-cookie'] || [];

  // 1.5 Get CSRF Token
  const csrfRes = await request(`${origin}/dashboard?_data=routes/_ui`, {
    method: 'GET',
    headers: {
      'sec-fetch-mode': 'navigate',
      'cookie': cookiesArr.map(c => c.split(';')[0]).join('; ')
    }
  });

  if (csrfRes.headers['set-cookie']) {
    cookiesArr = cookiesArr.concat(csrfRes.headers['set-cookie']);
  }
  
  let csrfToken = '';
  try {
    csrfToken = JSON.parse(csrfRes.data).csrfToken;
  } catch (e) {
    console.error("Failed to parse CSRF response");
    process.exit(1);
  }

  const cookies = cookiesArr.map(c => c.split(';')[0]).join('; ');

  // 2. Create project
  const projectBody = JSON.stringify({ "0": { title } });
  const projectRes = await request(`${origin}/trpc/project.create?batch=1`, {
    method: 'POST',
    headers: {
      'content-type': 'application/json',
      'sec-fetch-site': 'same-origin',
      'sec-fetch-mode': 'cors',
      'origin': origin,
      'cookie': cookies,
      'x-csrf-token': csrfToken,
      'content-length': Buffer.byteLength(projectBody)
    }
  }, projectBody);

  let projectData;
  try {
    projectData = JSON.parse(projectRes.data);
  } catch (e) {
    console.error("Failed to parse project.create response:");
    console.error(projectRes.data.substring(0, 300));
    process.exit(1);
  }

  if (!projectData[0] || !projectData[0].result || !projectData[0].result.data) {
    console.error("Unexpected project.create response shape:");
    console.error(projectRes.data.substring(0, 300));
    process.exit(1);
  }

  const projectId = projectData[0].result.data.id;

  // 3. Create token
  const tokenBody = JSON.stringify({
    "0": {
      projectId,
      relation: "builders",
      name: "clone",
      canUseApi: false
    }
  });

  const tokenRes = await request(`${origin}/trpc/authorizationToken.create?batch=1`, {
    method: 'POST',
    headers: {
      'content-type': 'application/json',
      'sec-fetch-site': 'same-origin',
      'sec-fetch-mode': 'cors',
      'origin': origin,
      'cookie': cookies,
      'x-csrf-token': csrfToken,
      'content-length': Buffer.byteLength(tokenBody)
    }
  }, tokenBody);

  let tokenData;
  try {
    tokenData = JSON.parse(tokenRes.data);
  } catch (e) {
    console.error("Failed to parse authorizationToken.create response:");
    console.error(tokenRes.data.substring(0, 300));
    process.exit(1);
  }

  if (!tokenData[0] || !tokenData[0].result || !tokenData[0].result.data) {
    console.error("Unexpected authorizationToken.create response shape:");
    console.error(tokenRes.data.substring(0, 300));
    process.exit(1);
  }

  // It returns an array because it's a supabase insert
  const tokenObj = Array.isArray(tokenData[0].result.data) ? tokenData[0].result.data[0] : tokenData[0].result.data;
  const token = tokenObj.token;

  // 4. Output
  const parsedOrigin = new URL(origin);
  const hostWithPort = parsedOrigin.host;
  const shareLink = `https://p-${projectId}.${hostWithPort}/?authToken=${token}`;
  
  console.log(JSON.stringify({
    projectId,
    token,
    shareLink,
    editorUrl: shareLink
  }));
}

run().catch(e => {
  console.error(e.message);
  process.exit(1);
});
