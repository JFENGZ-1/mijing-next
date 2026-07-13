(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageReport/component/head-hint/head-hint"],
  {
    2917: function (n, t, o) {
      "use strict";
      (function (n) {
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var e = o("4689"),
          i = {
            props: {
              updateTime: { type: String, default: "" },
              bgcolor: { type: String, default: "#FEF9DE" },
              color: { type: String, default: "#C96A2F" },
              show: { type: Boolean, default: !1 },
              type: { type: String, default: "1" },
              computeType: { type: String, default: "0" },
            },
            components: {
              confirm: function () {
                o.e("components/confirm-modal/confirm-modal")
                  .then(
                    function () {
                      return resolve(o("4e5b"));
                    }.bind(null, o),
                  )
                  .catch(o.oe);
              },
            },
            data: function () {
              return {};
            },
            methods: {
              succCconfirmbtn: function () {
                this.$refs.succConfirmModal.show = !1;
              },
              ljconsumption: function () {
                var t = this;
                1 == this.computeType
                  ? (0, e.ReComputeSalary)().then(function (o) {
                      200 == o.code
                        ? ((t.$refs.consumptionConfirmModal.show = !1),
                          (t.$refs.succConfirmModal.show = !0))
                        : n.showToast({ icon: "none", title: o.msg });
                    })
                  : 2 == this.computeType &&
                    (0, e.sumSaleSalary)().then(function (o) {
                      200 == o.code
                        ? ((t.$refs.consumptionConfirmModal.show = !1),
                          (t.$refs.succConfirmModal.show = !0))
                        : n.showToast({ icon: "none", title: o.msg });
                    });
              },
              consumptionhandleCancelbtn: function () {
                this.$refs.consumptionConfirmModal.show = !1;
              },
              refreshclick: function () {
                this.$refs.consumptionConfirmModal.show = !0;
              },
              confirmbtnFail: function () {
                this.$refs.confirmModal.show = !1;
              },
              dataexplain: function () {
                this.$refs.confirmModal.show = !0;
              },
            },
          };
        t.default = i;
      }).call(this, o("df3c").default);
    },
    "2fe4": function (n, t, o) {
      "use strict";
      (function (n, t) {
        var e = o("47a9");
        o("86d2"), e(o("3240"));
        var i = e(o("740f"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = o), t(i.default);
      }).call(this, o("3223").default, o("df3c").createPage);
    },
    "354d": function (n, t, o) {
      "use strict";
      o.r(t);
      var e = o("2917"),
        i = o.n(e);
      for (var c in e)
        ["default"].indexOf(c) < 0 &&
          (function (n) {
            o.d(t, n, function () {
              return e[n];
            });
          })(c);
      t.default = i.a;
    },
    "3ecb": function (n, t, o) {},
    "740f": function (n, t, o) {
      "use strict";
      o.r(t);
      var e = o("ef55"),
        i = o("354d");
      for (var c in i)
        ["default"].indexOf(c) < 0 &&
          (function (n) {
            o.d(t, n, function () {
              return i[n];
            });
          })(c);
      o("8d6a");
      var a = o("828b"),
        r = Object(a.a)(
          i.default,
          e.b,
          e.c,
          !1,
          null,
          "6756aa90",
          null,
          !1,
          e.a,
          void 0,
        );
      t.default = r.exports;
    },
    "8d6a": function (n, t, o) {
      "use strict";
      var e = o("3ecb");
      o.n(e).a;
    },
    ef55: function (n, t, o) {
      "use strict";
      o.d(t, "b", function () {
        return i;
      }),
        o.d(t, "c", function () {
          return c;
        }),
        o.d(t, "a", function () {
          return e;
        });
      var e = {
          confirmModal: function () {
            return o
              .e("components/confirm-modal/confirm-modal")
              .then(o.bind(null, "4e5b"));
          },
        },
        i = function () {
          var n = this,
            t =
              (n.$createElement,
              n._self._c,
              n.show && 0 == n.computeType && 1 == n.type
                ? n.imgsrc("imgs/202501/data_explain.png")
                : null),
            o =
              n.show && 0 == n.computeType && 2 == n.type
                ? n.imgsrc("imgs/202501/data_explain_green.png")
                : null;
          n.$mp.data = Object.assign({}, { $root: { m0: t, m1: o } });
        },
        c = [];
    },
  },
  [["2fe4", "common/runtime", "common/vendor"]],
]);
