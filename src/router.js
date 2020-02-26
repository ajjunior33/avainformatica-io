const { Router } = require("express");
const CadastrosController = require("./controllers/CadastroController");
const UsuariosController = require("./controllers/UsuariosController");

const router = Router();


router.get("/cadastros", CadastrosController.index);
router.post("/cadastros", CadastrosController.store);
router.delete("/cadastros/:id", CadastrosController.delete);

router.post("/usuarios", UsuariosController.store);
router.post("/auth", UsuariosController.auth);
router.get("/checkAuth/:token", UsuariosController.checkAuth);


module.exports = router;