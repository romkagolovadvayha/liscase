const express = require('express');
const ftp = require('basic-ftp');
const fs = require('fs');
const path = require('path');

const app = express();
app.use(express.json());

app.post('/download-map', async (req, res) => {
    const { host, user, password, remoteDir } = req.body;

    if (!host || !user || !password || !remoteDir) {
        return res.status(400).json({ error: 'Все поля обязательны: host, user, password, remoteDir' });
    }

    const client = new ftp.Client(20000); // 20 секунд
    client.ftp.verbose = true;
    const localDir = path.resolve(__dirname, '../../../frontend/web/maps');

    try {
        await client.access({
            host,
            user,
            password,
            secure: false
        });

        await client.cd(remoteDir);
        const files = await client.list();
        const mapFiles = files
            .filter(file => file.name.endsWith('.map'))
            .sort((a, b) => b.modifiedAt - a.modifiedAt);

        if (mapFiles.length === 0) {
            return res.status(404).json({ error: 'Файлы .map не найдены' });
        }

        const latestFile = mapFiles[0];
        const localPath = path.join(localDir, latestFile.name);

        if (!fs.existsSync(localDir)) {
            fs.mkdirSync(localDir, { recursive: true });
        }

        await client.downloadTo(localPath, latestFile.name);
        res.json({ success: true, file: latestFile.name, saved_to: localPath });
    } catch (err) {
        console.error(err);
        res.status(500).json({ error: 'Ошибка при подключении или загрузке файла', details: err.message });
    } finally {
        client.close();
    }
});

app.listen(3000, () => {
    console.log('Server started on http://localhost:3000');
});