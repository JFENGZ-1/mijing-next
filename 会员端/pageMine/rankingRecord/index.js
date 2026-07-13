(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageMine/rankingRecord/index"],
  {
    4057: function (n, e, t) {
      var u = t("b436");
      t.n(u).a;
    },
    "564f": function (n, e, t) {
      t.r(e);
      var u = t("a8aa"),
        i = t.n(u);
      for (var o in u)
        ["default"].indexOf(o) < 0 &&
          (function (n) {
            t.d(e, n, function () {
              return u[n];
            });
          })(o);
      e.default = i.a;
    },
    "767f": function (n, e, t) {
      (function (n, e) {
        var u = t("47a9");
        t("9785"), u(t("3240"));
        var i = u(t("bda5"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = t), e(i.default);
      }).call(this, t("3223").default, t("df3c").createPage);
    },
    a8aa: function (n, e, t) {
      Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.default = void 0);
      var u = t("888d"),
        i = {
          data: function () {
            return {
              indexnum: null,
              ncount: null,
              list: [],
              year: new Date().getFullYear(),
              month: new Date().getMonth() + 1,
              name: "",
              photo: "",
              totalCount: "",
              loadText: {
                loadmore: "轻轻上拉",
                loading: "努力加载中",
                nomore: "仅列出前15名会员",
              },
            };
          },
          computed: {},
          methods: {
            getList: function () {
              var n = this;
              (0, u.rankList)().then(function (e) {
                n.list = e.list.slice(0, 15);
                var t = e.myRank,
                  u = t.userRealname,
                  i = t.userFaceurl,
                  o = t.indexnum,
                  a = t.ncount;
                (n.name = u), (n.photo = i), (n.indexnum = o), (n.ncount = a);
              });
            },
          },
          onLoad: function () {
            this.getList();
          },
        };
      e.default = i;
    },
    b436: function (n, e, t) {},
    bda5: function (n, e, t) {
      t.r(e);
      var u = t("f0f0"),
        i = t("564f");
      for (var o in i)
        ["default"].indexOf(o) < 0 &&
          (function (n) {
            t.d(e, n, function () {
              return i[n];
            });
          })(o);
      t("4057");
      var a = t("828b"),
        r = Object(a.a)(
          i.default,
          u.b,
          u.c,
          !1,
          null,
          "61ff07f4",
          null,
          !1,
          u.a,
          void 0,
        );
      e.default = r.exports;
    },
    f0f0: function (n, e, t) {
      t.d(e, "b", function () {
        return i;
      }),
        t.d(e, "c", function () {
          return o;
        }),
        t.d(e, "a", function () {
          return u;
        });
      var u = {
          uIcon: function () {
            return t
              .e("node-modules/uview-ui/components/u-icon/u-icon")
              .then(t.bind(null, "e4b0"));
          },
          uImage: function () {
            return t
              .e("node-modules/uview-ui/components/u-image/u-image")
              .then(t.bind(null, "bc62"));
          },
          uLoadmore: function () {
            return t
              .e("node-modules/uview-ui/components/u-loadmore/u-loadmore")
              .then(t.bind(null, "ffa0"));
          },
        },
        i = function () {
          var n = this,
            e =
              (n.$createElement,
              n._self._c,
              n.imgsrc("/static/imgs/bg002.png")),
            t = n.__map(n.list, function (e, t) {
              return {
                $orig: n.__get_orig(e),
                m1:
                  1 == e.indexnum
                    ? n.imgsrc("/static/imgs/member_num0.png")
                    : null,
                m2:
                  2 == e.indexnum
                    ? n.imgsrc("/static/imgs/member_num1.png")
                    : null,
                m3:
                  3 == e.indexnum
                    ? n.imgsrc("/static/imgs/member_num2.png")
                    : null,
                m4: e.isMe ? n.imgsrc("/static/imgs/c-self-img.png") : null,
              };
            });
          n.$mp.data = Object.assign({}, { $root: { m0: e, l0: t } });
        },
        o = [];
    },
  },
  [["767f", "common/runtime", "common/vendor"]],
]);
