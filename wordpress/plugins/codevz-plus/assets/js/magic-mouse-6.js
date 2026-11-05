class Codevz_Glitch_Effect {
  constructor() {
    this.root = document.body
    this.cursor = document.querySelector(".curzr")

    this.distanceX = 0, 
    this.distanceY = 0,
    this.pointerX = 0,
    this.pointerY = 0,
    this.previousPointerX = 0
    this.previousPointerY = 0
    this.cursorSize = 25

    this.color1 = ( this.cursor.getAttribute( 'data-color-1' ) || '#00feff' )
    this.color2 = ( this.cursor.getAttribute( 'data-color-2' ) || '#ff4f71' )

    this.glitchColorB = this.color1
    this.glitchColorR = this.color2

    this.cursorStyle = {
      boxSizing: 'border-box',
      position: 'fixed',
      top: `${ this.cursorSize / -2 }px`,
      left: `${ this.cursorSize / -2 }px`,
      zIndex: '2147483647',
      width: `${ this.cursorSize }px`,
      height: `${ this.cursorSize }px`,
      backgroundColor: '#222',
      borderRadius: '50%',
      boxShadow: `0 0 0 ${this.glitchColorB}, 0 0 0 ${this.glitchColorR}`,
      transition: '100ms, transform 100ms, box-shadow 50ms',
      userSelect: 'none',
      pointerEvents: 'none'
    }

    if (CSS.supports("backdrop-filter", "invert(1)")) {
      this.cursorStyle.backdropFilter = 'invert(1)'
      this.cursorStyle.backgroundColor = '#fff0'
    } else {
      this.cursorStyle.backgroundColor = this.color1
    }

    this.init(this.cursor, this.cursorStyle)
  }

  init(el, style) {
    Object.assign(el.style, style)
    this.cursor.removeAttribute("hidden")
    
  }

  move(event) {
    this.previousPointerX = this.pointerX
    this.previousPointerY = this.pointerY
    this.pointerX = event.pageX + this.root.getBoundingClientRect().x
    this.pointerY = event.pageY + this.root.getBoundingClientRect().y
    this.distanceX = Math.min(Math.max(this.previousPointerX - this.pointerX, -10), 10)
    this.distanceY = Math.min(Math.max(this.previousPointerY - this.pointerY, -10), 10)

    if (event.target.localName === 'button' || 
        event.target.localName === 'a' || 
        event.target.onclick !== null ||
        event.target.className.includes('curzr-hover')) {
      this.hover()
    } else {
      this.hoverout()
    }

    this.cursor.style.transform = `translate3d(${this.pointerX}px, ${this.pointerY-30}px, 0)`
    this.cursor.style.boxShadow = `
      ${+this.distanceX}px ${+this.distanceY}px 0 ${this.glitchColorB}, 
      ${-this.distanceX}px ${-this.distanceY}px 0 ${this.glitchColorR}`
    this.stop()
  }

  hover() {
  }

  hoverout() {
  }

  click() {
    this.cursor.style.transform += ` scale(0.5)`
    setTimeout(() => {
      this.cursor.style.transform = this.cursor.style.transform.replace(` scale(0.5)`, '')
    }, 50)
  }

  stop() {
    if (!this.moving) {
      this.moving = true
      setTimeout(() => {
        this.cursor.style.boxShadow = ''
        this.moving = false
      }, 50)
    }
  }

  remove() {
    this.cursor.remove()
  }
}

(() => {
  const cursor = new Codevz_Glitch_Effect()
  if(!/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
    document.onmousemove = function (event) {
      cursor.move(event)
    }
    document.onclick = function () {
      cursor.click()
    }
  } else {
    cursor.remove()
  }
})()