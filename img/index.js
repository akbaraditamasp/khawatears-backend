const sharp = require("sharp");
const http = require("http");
const url = require("url");
const path = require("path");
const { Readable } = require("stream");

const port = process.env.PORT || 2020;

const imageGeneration = (loc, width, height, fit = "cover") =>
  new Promise((resolve, reject) => {
    sharp(path.join(__dirname, "..", "uploads", loc))
      .resize({
        width,
        height,
        fit: fit === "cover" ? sharp.fit.cover : sharp.fit.contain,
        position: sharp.strategy.entropy,
        background: {
          r: 0,
          g: 0,
          b: 0,
          alpha: 0,
        },
      })
      .webp({ quality: 50 })
      .toBuffer()
      .then((data) => {
        resolve(Readable.from(data));
      })
      .catch((e) => {
        reject(e);
      });
  });

const server = http.createServer(async (req, res) => {
  const query = url.parse(req.url, true).query;

  const wh = (query.size || "300x300").split("x");
  const fit = query.fit || "cover";
  const width = wh[0] || 300;
  const height = wh[1] || 300;

  if (!query.url) return res.end("Invalid");
  let image;
  try {
    image = await imageGeneration(
      query.url,
      Number(width),
      Number(height),
      fit
    );
    return image.pipe(res);
  } catch (e) {
    console.log(e);
    return res.end("Invalid");
  }
});

server.listen(port, () => {
  console.log(`Server running`);
});
