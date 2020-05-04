const { Router } = require("express");

const route = Router();

const mercadoPagoController = require("./app/controllers/mercadoPago");
const ClientesController = require("./app/controllers/ClientesController");
route.get("/mercadoPago", mercadoPagoController.taxaCartao);
route.get("/clientes", ClientesController.index);
route.post("/clientes", ClientesController.store);
route.put("/clientes", ClientesController.update);

module.exports = route;
