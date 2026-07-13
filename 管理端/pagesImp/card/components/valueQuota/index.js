(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesImp/card/components/valueQuota/index"],
  {
    "343f": function (t, n, o) {
      "use strict";
      (function (t) {
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var e = {
          data: function () {
            return {
              show: !1,
              formData: {
                totalAmount: "",
                cardAmount: "",
                discount: "",
                presentAmount: "",
              },
              swData: { discount: !1, presentAmount: !1 },
            };
          },
          components: {
            add: function () {
              o.e("pagesImp/card/components/valueQuota/add")
                .then(
                  function () {
                    return resolve(o("4163"));
                  }.bind(null, o),
                )
                .catch(o.oe);
            },
          },
          watch: { show: function (t) {} },
          created: function () {},
          methods: {
            addTime: function (t) {
              this.formData.discount = t;
            },
            handleCardAmountInput: function (t) {
              var n = this,
                o = t.detail ? t.detail.value : t;
              this.$nextTick(function () {
                var t = o.replace(/[^\d.]/g, ""),
                  e = t.indexOf(".");
                if (-1 !== e) {
                  var a = t.substring(0, e),
                    u = t.substring(e + 1).replace(/\./g, "");
                  t = a + "." + u;
                }
                t !== n.formData.cardAmount && (n.formData.cardAmount = t);
              });
            },
            handlePresentAmountInput: function (t) {
              var n = this,
                o = t.detail ? t.detail.value : t;
              this.$nextTick(function () {
                var t = o.replace(/[^\d.]/g, ""),
                  e = t.indexOf(".");
                if (-1 !== e) {
                  var a = t.substring(0, e),
                    u = t.substring(e + 1).replace(/\./g, "");
                  t = a + "." + u;
                }
                t !== n.formData.presentAmount &&
                  (n.formData.presentAmount = t);
              });
            },
            switchHandle: function (t) {
              this.formData[t] = "";
            },
            submit: function () {
              var n = "";
              this.swData.discount &&
                !this.formData.discount &&
                (n = "请输入折扣"),
                this.swData.presentAmount &&
                  !this.formData.presentAmount &&
                  (n = "请输入赠送金额"),
                0 === this.formData.cardAmount ||
                  this.formData.cardAmount ||
                  (n = "请输入卡内金额"),
                n
                  ? t.showToast({ title: n, duration: 2e3, icon: "none" })
                  : ((this.formData.totalAmount =
                      Number(this.formData.cardAmount) +
                      Number(this.formData.presentAmount)),
                    this.$emit("submit", this.formData),
                    (this.show = !1));
            },
            open: function (t) {
              t &&
                (this.$set(this.$data, "swData", {
                  discount: t.discount > 0,
                  presentAmount: t.presentAmount > 0,
                }),
                this.$set(this.$data, "formData", {
                  cardAmount:
                    0 === t.cardAmount || t.cardAmount
                      ? t.cardAmount
                      : this.formData.cardAmount,
                  discount: t.discount ? t.discount : this.formData.discount,
                  presentAmount: t.presentAmount
                    ? t.presentAmount
                    : this.formData.presentAmount,
                })),
                (this.show = !0);
            },
          },
          computed: {},
        };
        n.default = e;
      }).call(this, o("df3c").default);
    },
    7908: function (t, n, o) {},
    9735: function (t, n, o) {
      "use strict";
      o.r(n);
      var e = o("d2f3"),
        a = o("adba");
      for (var u in a)
        ["default"].indexOf(u) < 0 &&
          (function (t) {
            o.d(n, t, function () {
              return a[t];
            });
          })(u);
      o("afcd");
      var i = o("828b"),
        r = Object(i.a)(
          a.default,
          e.b,
          e.c,
          !1,
          null,
          "f06531aa",
          null,
          !1,
          e.a,
          void 0,
        );
      n.default = r.exports;
    },
    adba: function (t, n, o) {
      "use strict";
      o.r(n);
      var e = o("343f"),
        a = o.n(e);
      for (var u in e)
        ["default"].indexOf(u) < 0 &&
          (function (t) {
            o.d(n, t, function () {
              return e[t];
            });
          })(u);
      n.default = a.a;
    },
    afcd: function (t, n, o) {
      "use strict";
      var e = o("7908");
      o.n(e).a;
    },
    d2f3: function (t, n, o) {
      "use strict";
      o.d(n, "b", function () {
        return a;
      }),
        o.d(n, "c", function () {
          return u;
        }),
        o.d(n, "a", function () {
          return e;
        });
      var e = {
          ffPopup: function () {
            return o
              .e("components/ff-popup/ff-popup")
              .then(o.bind(null, "c29b"));
          },
          uInput: function () {
            return Promise.all([
              o.e("common/vendor"),
              o.e("uview-ui/components/u-input/u-input"),
            ]).then(o.bind(null, "b5ea"));
          },
          uSwitch: function () {
            return o
              .e("uview-ui/components/u-switch/u-switch")
              .then(o.bind(null, "a048"));
          },
          uButton: function () {
            return o
              .e("uview-ui/components/u-button/u-button")
              .then(o.bind(null, "d5d3"));
          },
        },
        a = function () {
          var t = this;
          t.$createElement;
          t._self._c,
            t._isMounted ||
              (t.e0 = function (n) {
                return t.$refs.add.open(t.formData.discount);
              });
        },
        u = [];
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pagesImp/card/components/valueQuota/index-create-component",
    {
      "pagesImp/card/components/valueQuota/index-create-component": function (
        t,
        n,
        o,
      ) {
        o("df3c").createComponent(o("9735"));
      },
    },
    [["pagesImp/card/components/valueQuota/index-create-component"]],
  ]);
