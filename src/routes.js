const { Router } = require("express");

const route = Router();

const mercadoPagoController = require("./app/controllers/mercadoPago");
route.get("/mercadoPago", mercadoPagoController.taxaCartao);

module.exports = route;
