(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesImp/shop/staff/components/identity-popup"],
  {
    "0c31": function (t, n, e) {},
    "7ed7f": function (t, n, e) {
      "use strict";
      e.d(n, "b", function () {
        return u;
      }),
        e.d(n, "c", function () {
          return o;
        }),
        e.d(n, "a", function () {
          return i;
        });
      var i = {
          ffPopup: function () {
            return e
              .e("components/ff-popup/ff-popup")
              .then(e.bind(null, "c29b"));
          },
          uSwitch: function () {
            return e
              .e("uview-ui/components/u-switch/u-switch")
              .then(e.bind(null, "a048"));
          },
          uLine: function () {
            return e
              .e("uview-ui/components/u-line/u-line")
              .then(e.bind(null, "fac3"));
          },
          uButton: function () {
            return e
              .e("uview-ui/components/u-button/u-button")
              .then(e.bind(null, "d5d3"));
          },
        },
        u = function () {
          this.$createElement;
          this._self._c;
        },
        o = [];
    },
    93446: function (t, n, e) {
      "use strict";
      var i = e("0c31");
      e.n(i).a;
    },
    bf87: function (t, n, e) {
      "use strict";
      e.r(n);
      var i = e("c56a"),
        u = e.n(i);
      for (var o in i)
        ["default"].indexOf(o) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return i[t];
            });
          })(o);
      n.default = u.a;
    },
    c56a: function (t, n, e) {
      "use strict";
      Object.defineProperty(n, "__esModule", { value: !0 }),
        (n.default = void 0),
        (n.default = {
          data: function () {
            return { identityShow: !1, identity1: 0, identity2: 0 };
          },
          methods: {
            findIdentity: function (t) {
              var n = this;
              (this.identity1 = 0),
                (this.identity2 = 0),
                t.forEach(function (t) {
                  1 == t && (n.identity1 = 1), 2 == t && (n.identity2 = 1);
                }),
                (this.identityShow = !0);
            },
            submit: function () {
              var t = [],
                n = "";
              1 == this.identity1 && (t.push(1), (n = "教练")),
                1 == this.identity2 &&
                  (t.push(2),
                  "" != n ? (n += " | 会籍顾问") : (n = "会籍顾问")),
                "" == n && (n = "内务"),
                this.$emit("saveIdentityList", t, n),
                (this.identityShow = !1);
            },
          },
        });
    },
    f796: function (t, n, e) {
      "use strict";
      e.r(n);
      var i = e("7ed7f"),
        u = e("bf87");
      for (var o in u)
        ["default"].indexOf(o) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return u[t];
            });
          })(o);
      e("93446");
      var c = e("828b"),
        f = Object(c.a)(
          u.default,
          i.b,
          i.c,
          !1,
          null,
          "4ebf2080",
          null,
          !1,
          i.a,
          void 0,
        );
      n.default = f.exports;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pagesImp/shop/staff/components/identity-popup-create-component",
    {
      "pagesImp/shop/staff/components/identity-popup-create-component":
        function (t, n, e) {
          e("df3c").createComponent(e("f796"));
        },
    },
    [["pagesImp/shop/staff/components/identity-popup-create-component"]],
  ]);
