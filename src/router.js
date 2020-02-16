const { Router } = require("express");
const CadastrosController = require("./controllers/CadastroController");

const router = Router();

router.get("/", (req, res) =>{
    res.json("hello, world");
});

router.get("/cadastros", CadastrosController.index);
router.post("/cadastros", CadastrosController.store);
router.delete("/cadastros/:id", CadastrosController.delete);
module.exports = router;