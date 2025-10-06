// Install required packages: npm install express cors dotenv node-fetch
const express = require('express');
const cors = require('cors');
// Use the native global fetch in Node.js 18+, or require it if using an older version
const fetch = require('node-fetch');
// Use the port provided by Render environment, defaulting to 3000 for local dev
const PORT = process.env.PORT || 3000;
const COMPILER_API_URL = "https://emkc.org/api/v2/piston/execute";

// Get the key from Render's environment variables.
// The key is NOT visible in the server.js file itself.
const COMPILER_API_KEY = process.env.COMPILER_API_KEY; 

if (!COMPILER_API_KEY) {
    console.error("CRITICAL ERROR: COMPILER_API_KEY environment variable is not set.");
    // In a real application, you'd exit immediately.
}

const app = express();

// Configure CORS to ONLY allow requests from your Vercel frontend domain
// IMPORTANT: Change 'https://code.downverse.in' to your actual Vercel domain!
app.use(cors({
    origin: 'https://code.downverse.in' 
}));

app.use(express.json());

// API endpoint that the frontend will call
app.post('/api/execute', async (req, res) => {
    // Basic security and input validation
    const { language, version, files, stdin, args } = req.body;

    if (!language || !version || !Array.isArray(files) || files.length === 0) {
        return res.status(400).json({ error: "Missing required compilation parameters." });
    }

    if (!COMPILER_API_KEY) {
        return res.status(503).json({ error: "Compiler service unavailable (API Key not configured)." });
    }

    try {
        // Forward the request to the external compiler API with the SECRET key
        const externalResponse = await fetch(COMPILER_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                // 🛑 KEY IS HIDDEN HERE ON THE SERVER 🛑
                'Authorization': `Bearer ${COMPILER_API_KEY}`
            },
            body: JSON.stringify({ language, version, files, stdin: stdin || "", args: args || [] })
        });

        // Send the external API's response (including status and body) back to the frontend
        const apiResult = await externalResponse.json();
        res.status(externalResponse.status).json(apiResult);

    } catch (error) {
        console.error('Proxy execution error:', error);
        res.status(500).json({ 
            error: 'An internal server error occurred while connecting to the compiler.',
            details: error.message
        });
    }
});

// A simple health check route for Render
app.get('/', (req, res) => {
    res.send('Compiler Proxy is running.');
});

app.listen(PORT, () => {
    console.log(`Proxy server listening on port ${PORT}`);
});