(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageCourse/clusterCourse/components/member-list"],
  {
    3872: function (n, e, t) {
      Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.default = void 0);
      var o = {
        data: function () {
          return {
            show: !1,
            confirBtnStyle: { width: "458rpx", height: "83rpx" },
            list: [],
          };
        },
        components: {
          Dialog: function () {
            t.e("components/dialog/index")
              .then(
                function () {
                  return resolve(t("562b"));
                }.bind(null, t),
              )
              .catch(t.oe);
          },
        },
        methods: {
          open: function (n) {
            (this.list = []), (this.list = n), (this.show = !0);
          },
          confirm: function () {
            this.show = !1;
          },
        },
      };
      e.default = o;
    },
    "3a1d": function (n, e, t) {
      t.d(e, "b", function () {
        return u;
      }),
        t.d(e, "c", function () {
          return r;
        }),
        t.d(e, "a", function () {
          return o;
        });
      var o = {
          uButton: function () {
            return t
              .e("node-modules/uview-ui/components/u-button/u-button")
              .then(t.bind(null, "be1a"));
          },
        },
        u = function () {
          var n = this,
            e =
              (n.$createElement,
              n._self._c,
              n.__map(n.list, function (e, t) {
                return {
                  $orig: n.__get_orig(e),
                  m0: n.$shorten(e.userNickname, 10),
                };
              }));
          n.$mp.data = Object.assign({}, { $root: { l0: e } });
        },
        r = [];
    },
    "7b11": function (n, e, t) {},
    "7cf4": function (n, e, t) {
      t.r(e);
      var o = t("3a1d"),
        u = t("e622");
      for (var r in u)
        ["default"].indexOf(r) < 0 &&
          (function (n) {
            t.d(e, n, function () {
              return u[n];
            });
          })(r);
      t("d0f1");
      var c = t("828b"),
        i = Object(c.a)(
          u.default,
          o.b,
          o.c,
          !1,
          null,
          "bd0b23be",
          null,
          !1,
          o.a,
          void 0,
        );
      e.default = i.exports;
    },
    d0f1: function (n, e, t) {
      var o = t("7b11");
      t.n(o).a;
    },
    e622: function (n, e, t) {
      t.r(e);
      var o = t("3872"),
        u = t.n(o);
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (n) {
            t.d(e, n, function () {
              return o[n];
            });
          })(r);
      e.default = u.a;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pageCourse/clusterCourse/components/member-list-create-component",
    {
      "pageCourse/clusterCourse/components/member-list-create-component":
        function (n, e, t) {
          t("df3c").createComponent(t("7cf4"));
        },
    },
    [["pageCourse/clusterCourse/components/member-list-create-component"]],
  ]);
