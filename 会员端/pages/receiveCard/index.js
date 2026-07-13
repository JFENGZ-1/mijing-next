(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/receiveCard/index"],
  {
    "0f50": function (e, t, n) {
      (function (e, t) {
        var i = n("47a9");
        n("9785"), i(n("3240"));
        var a = i(n("a589"));
        (e.__webpack_require_UNI_MP_PLUGIN__ = n), t(a.default);
      }).call(this, n("3223").default, n("df3c").createPage);
    },
    "179f": function (e, t, n) {
      var i = n("5652");
      n.n(i).a;
    },
    5652: function (e, t, n) {},
    a589: function (e, t, n) {
      n.r(t);
      var i = n("c8b7"),
        a = n("ae25");
      for (var r in a)
        ["default"].indexOf(r) < 0 &&
          (function (e) {
            n.d(t, e, function () {
              return a[e];
            });
          })(r);
      n("179f");
      var o = n("828b"),
        s = Object(o.a)(
          a.default,
          i.b,
          i.c,
          !1,
          null,
          "98c6b842",
          null,
          !1,
          i.a,
          void 0,
        );
      t.default = s.exports;
    },
    ae25: function (e, t, n) {
      n.r(t);
      var i = n("bee8"),
        a = n.n(i);
      for (var r in i)
        ["default"].indexOf(r) < 0 &&
          (function (e) {
            n.d(t, e, function () {
              return i[e];
            });
          })(r);
      t.default = a.a;
    },
    bee8: function (e, t, n) {
      (function (e) {
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var i = n("f46d"),
          a = {
            components: {
              memberCard: function () {
                n.e("components/mumber-card/index")
                  .then(
                    function () {
                      return resolve(n("cbab"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
              hint: function () {
                n.e("pages/receiveCard/components/hint")
                  .then(
                    function () {
                      return resolve(n("e044"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
              loadingPulse: function () {
                n.e("components/loading/loading-pulse")
                  .then(
                    function () {
                      return resolve(n("eb51"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
            },
            data: function () {
              return {
                cardLoadion: !0,
                img: "/static/imgs/background.jpg",
                userId: "",
                userPhone: "",
                type: 1,
                info: { cardlist: null },
                validmsg: "",
                isTake: !1,
                isExpire: !1,
                phoneNumber: "",
                currentPhoneNumber: "",
                sharekey: "",
                siteName: "",
                time: 5,
              };
            },
            computed: {
              StatusBar: function () {
                return this.$store.state.systemInfo.statusBarHeight;
              },
              CustomBar: function () {
                var t = e.getMenuButtonBoundingClientRect();
                return (
                  t.height +
                  2 * (t.top - this.$store.state.systemInfo.statusBarHeight) +
                  2
                );
              },
            },
            methods: {
              toggleCard: function (e, t) {
                if (t != this.info.cardlist.length - 1) {
                  var n = [
                    this.info.cardlist[this.info.cardlist.length - 1],
                    this.info.cardlist[t],
                  ];
                  (this.info.cardlist[t] = n[0]),
                    (this.info.cardlist[this.info.cardlist.length - 1] = n[1]),
                    this.$forceUpdate();
                }
              },
              back: function () {
                e.navigateBackMiniProgram();
              },
              backHome: function () {
                e.reLaunch({ url: "/pages/start/index" });
              },
              receiveCard: function () {
                if (1 == this.type || this.isExpire) return !1;
                e.setStorage({
                  key: "cardId",
                  data: this.sharekey,
                  success: function (t) {
                    e.navigateTo({
                      url: "/pages/receiveCard/authorization/info/index",
                    });
                  },
                });
              },
              getCardInfo: function (t) {
                var n = this;
                (0, i.getUserCardInfo)({ sharekey: this.sharekey }).then(
                  function (t) {
                    if (200 == t.code) {
                      console.log(t);
                      var i = t.cardlist,
                        a = t.validmsg,
                        r = (t.valid, t.isTake),
                        o = t.userPhone;
                      (n.info.cardlist = i),
                        (n.isTake = r),
                        (n.validmsg = a),
                        (n.phoneNumber = o),
                        i &&
                          i.length > 0 &&
                          ((n.userId = i[0].userId),
                          (n.siteName = i[0].siteName)),
                        (n.cardLoadion = !1);
                    } else if (501 == t.code) {
                      (n.cardLoadion = !1), (n.isExpire = !0), (n.time = 5);
                      var s = n,
                        c = setInterval(function () {
                          s.time--,
                            s.time <= 0 && (clearInterval(c), s.backHome());
                        }, 1e3);
                    } else
                      (n.cardLoadion = !1),
                        e.showToast({ title: t.msg, icon: "none", mask: !0 });
                  },
                );
              },
              refresh: function () {
                this.getCardInfo();
              },
            },
            onShareAppMessage: function () {
              return (
                console.log(
                  "/pages/receiveCard/index?sharekey=".concat(
                    this.sharekey,
                    "&type=",
                    2,
                  ),
                ),
                {
                  title: "".concat(this.siteName, " 给您发卡了"),
                  path: "/pages/receiveCard/index?sharekey=".concat(
                    this.sharekey,
                    "&type=",
                    2,
                  ),
                }
              );
            },
            onLoad: function (t) {
              console.log(t), t.type;
              var n = t.sharekey;
              (this.type = t.type),
                (this.sharekey = t.sharekey),
                this.getCardInfo(n),
                e.hideHomeButton();
              var i = this;
              e.$on("errorCallback", function (e) {
                (i.currentPhoneNumber = e.phoneNumber), i.$refs.hint.open();
              });
            },
            onUnload: function () {
              e.$off("errorCallback");
            },
          };
        t.default = a;
      }).call(this, n("df3c").default);
    },
    c8b7: function (e, t, n) {
      n.d(t, "b", function () {
        return i;
      }),
        n.d(t, "c", function () {
          return a;
        }),
        n.d(t, "a", function () {});
      var i = function () {
          var e = this,
            t =
              (e.$createElement,
              e._self._c,
              e.imgsrc("/static/imgs/background.jpg")),
            n = 2 == e.type ? e.imgsrc("/static/imgs/back_home.png") : null,
            i = e.info.cardlist && e.info.cardlist.length > 0,
            a =
              i && 2 == e.type && e.isTake
                ? e.imgsrc("/static/imgs/not-receive.png")
                : null,
            r = i ? e.info.cardlist.length : null,
            o = !i || r <= 1 ? null : e.info.cardlist.length,
            s = i ? null : e.imgsrc("/static/imgs/nocard.png"),
            c =
              e.cardLoadion || e.isExpire
                ? null
                : e.info.cardlist && e.info.cardlist.length > 0,
            u = e.info.cardlist && e.info.cardlist.length > 0 && !e.isExpire,
            l = 1 == e.type ? e.imgsrc("/static/imgs/forward.png") : null,
            d = 1 == e.type ? e.imgsrc("/static/imgs/go_back.png") : null;
          e.$mp.data = Object.assign(
            {},
            {
              $root: {
                m0: t,
                m1: n,
                g0: i,
                m2: a,
                g1: r,
                g2: o,
                m3: s,
                g3: c,
                g4: u,
                m4: l,
                m5: d,
              },
            },
          );
        },
        a = [];
    },
  },
  [["0f50", "common/runtime", "common/vendor"]],
]);
