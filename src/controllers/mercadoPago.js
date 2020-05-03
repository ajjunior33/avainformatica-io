const mercadoPago = require("../json/mercado_pago.json");

module.exports = {
  async taxaCartao(req, res) {
    if (process.env.AUTH !== req.headers.authorization) {
      return res.status(401).json({ messager: "Você precisa se autenticar." });
    }

    let { parcelamento, compensacao, valor, q } = req.query;
    let newValue = 0;

    let comp = mercadoPago.compensacao[compensacao];
    let parce = mercadoPago.parcelamento[`${parcelamento}x`];

    valor = parseFloat(valor);
    let compensacaoValue = (valor * comp) / 100;
    let parcelamentoValue = (valor * parce) / 100;
    let taxa = compensacaoValue + parcelamentoValue;

    if (q === "client") {
      newValue = valor + compensacaoValue + parcelamentoValue;
    } else {
      newValue = valor - compensacaoValue - parcelamentoValue;
    }
    newValue = parseFloat(newValue);
    newValue = Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(newValue);

    taxa = Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(taxa);
    parcelamentoValue = Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(parcelamentoValue);
    compensacaoValue = Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(compensacaoValue);

    return res.json({
      compensacao: compensacaoValue,
      parcelamentoValue,
      newValue,
      taxa,
    });
  },
};
